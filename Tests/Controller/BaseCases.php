<?php
/*
 * This file is part of the BaseApi package.
 *
 * (c) Juan Manuel Torres <kinojman@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Onema\BaseApiBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use FOS\Rest\Util\Codes;

/**
 * @author  Juan Manuel Torres <kinojman@gmail.com>
 */
class BaseCases extends WebTestCase
{
    protected $uri;
    protected $uriSingular;
    protected $controllerPlural;
    protected $controllerSingular;
    protected $prefix;
    
    protected $putParameters;  
    protected $postParameters;
    
    static $createdResources = array();

    public function setUp()
    {
        $this->uri = '/' . $this->prefix . $this->controllerPlural; 
        $this->uriSingular = '/' . $this->prefix . $this->controllerSingular;
    }
    
    public function testPostOne()
    {
        foreach($this->postParameters as $parameters) {
            
            $client = static::createClient();
            $client->request('POST', $this->uriSingular, $parameters);
            $response = json_decode($client->getResponse()->getContent());
            
            print_r($response);
            
            // Ensure that call returns a 201 Created status code. 
            $code = $client->getResponse()->getStatusCode();
            $this->assertEquals(Codes::HTTP_CREATED, $code);

            $headers = $client->getResponse()->headers;
            $location = $headers->get('Location');
            $parts = explode('/', $location);
            $size = sizeof($parts);
            $id = $parts[$size-1];

            // assert an ID with alpha numeric format: mongodb ids.
            $this->assertRegExp(
                '/^[a-zA-Z\d]+$/',
                $id
            );
            
            self::$createdResources[$location] = $id;
        }
        
        
        return self::$createdResources;
    }
    
    /**
     * @depends testPostOne
     */
    public function testGetCreatedContent($values)
    {
        foreach ($values as $location => $id) {
            $client = static::createClient();
            $client->request('GET', $location);

            $response = json_decode($client->getResponse()->getContent());
            $code = $client->getResponse()->getStatusCode();
            $this->assertEquals(Codes::HTTP_OK, $code);
        }
        
        // return the last id so the next test can update it. 
        return $values;
    }
    
    /**
     * @depends testGetCreatedContent
     * @todo check values agains the old ones to ensure they where update correctly.
     */
    public function testPutOne($values)
    {
        $id = array_pop($values);
        
        $client = static::createClient();
        $client->request('PUT', $this->uri . '/' . $id, $this->putParameters);
        $code = $client->getResponse()->getStatusCode();
        $this->assertEquals(Codes::HTTP_NO_CONTENT, $code);
    }
    
    public function testGetCollection()
    {
        $minimunSize = sizeof($this->putParameters)-1;
        $client = static::createClient();
        $client->request('GET', $this->uri);
        
        $this->assertRegExp('/'.$this->controllerPlural.'/', $client->getResponse()->getContent());
        
        $response = json_decode($client->getResponse()->getContent());
        $this->assertGreaterThan(
            $minimunSize,
            sizeof($response)
        );
    }
    
    public function testGetCollectionPaginated()
    {
        $paginationSize = sizeof($this->postParameters);
        $client = static::createClient();
        $client->request('GET', $this->uri, array('skip'=>0, 'limit' => $paginationSize));
        $response = json_decode($client->getResponse()->getContent());
        
        $objectName = $this->controllerPlural;
        $resultCount = count($response->$objectName);
        $this->assertEquals($paginationSize, $resultCount);
        
        $code = $client->getResponse()->getStatusCode();
        $this->assertEquals(Codes::HTTP_OK, $code);
        
        return $response;
    }
    
    /**
     * @depends testGetCollectionPaginated
     */
    public function testGetCollectionPaginatedSkip($response)
    {
        $paginationSize = sizeof($this->postParameters) - 1;
        
        $client = static::createClient();
        $client->request('GET', $this->uri, array('skip' => 1, 'limit' => $paginationSize));
        $responseSkipped = json_decode($client->getResponse()->getContent());
        
        $objectName = $this->controllerPlural;
        $resultCount = count($responseSkipped->$objectName);
        
        $this->assertEquals($paginationSize, $resultCount);

        // check if the objects from response 1 subindex 1 match response 2 subindex 0.
//        if(isset($response->$objectName[0]['id'])) {
//            $id1 = $response->$objectName[1]['id'];
//            $id2 = $responseSkipped->$objectName[0]['id'];
//            $this->assertEquals($id1, $id2);
//        }
        
        $code = $client->getResponse()->getStatusCode();
        $this->assertEquals(Codes::HTTP_OK, $code);
    }
    
    public function testGetAll()
    {
        $client = static::createClient();
        $client->request('GET', $this->uri);
        $response = json_decode($client->getResponse()->getContent());
        
        $objectName = $this->controllerPlural;
        $resultCount = count($response->$objectName);
        $this->assertGreaterThan(1, $resultCount);
        
        $code = $client->getResponse()->getStatusCode();
        $this->assertEquals(Codes::HTTP_OK, $code);
    }
    
    public function testZeroPagination()
    {
        $client = static::createClient();
        $client->request('GET', $this->uri, array('skip'=>0, 'limit' => 0));
        $response = json_decode($client->getResponse()->getContent());
        
        
        $objectName = $this->controllerPlural;
        $resultCount = count($response->$objectName);
        $this->assertGreaterThan(0, $resultCount);
        
        $code = $client->getResponse()->getStatusCode();
        $this->assertEquals(Codes::HTTP_OK, $code);
    }
    
    public function testGetInvalidPagination()
    {
        $client = static::createClient();
        $client->request('GET', $this->uri, array('skip'=>-1, 'limit' => -1));
        
        $code = $client->getResponse()->getStatusCode();
        $this->assertEquals(500, $code);
    }
    
    public function testGetDocumentWithBadId()
    {
        $client = static::createClient();
        $client->request('GET', $this->uri . '/0');
        
        $code = $client->getResponse()->getStatusCode();
        $this->assertEquals(404, $code);
    }
    
    public function testDeleteCreatedResources() 
    {
        $values = self::$createdResources;
        
        foreach ($values as $location => $id) {
            $client = static::createClient();
            $client->request('DELETE', $location);

            $code = $client->getResponse()->getStatusCode();
            $this->assertEquals(Codes::HTTP_NO_CONTENT, $code);
        }
        
        return $values;
    }
    
    /**
     * @depends testDeleteCreatedResources
     */
    public function testGetDeleteContent($values)
    {
        foreach ($values as $location => $id) {
            $client = static::createClient();
            $client->request('GET', $location);

            $response = json_decode($client->getResponse()->getContent());
            $code = $client->getResponse()->getStatusCode();
            $this->assertEquals(Codes::HTTP_NOT_FOUND, $code);
        }
    }
}
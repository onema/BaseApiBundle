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
    
    protected $putParameters;  
    protected $postParameters;


    public function setUp()
    {
        $this->uri = '/api/' . $this->controllerPlural; 
        $this->uriSingular = '/api/' . $this->controllerSingular;
    }
    
    public function testGetCollection()
    {
        $client = static::createClient();
        $crawler = $client->request('GET', $this->uri);
        $this->assertRegExp('/'.$this->controllerPlural.'/', $client->getResponse()->getContent());
    }
    
    public function testGetCollectionPaginated()
    {
        $client = static::createClient();
        $crawler = $client->request('GET', $this->uri, array('skip'=>0, 'limit' => 5));
        $response = json_decode($client->getResponse()->getContent());
        
        $objectName = $this->controllerPlural;
        $resultCount = count($response->$objectName);
        $this->assertEquals(5, $resultCount);
        
        $code = $client->getResponse()->getStatusCode();
        $this->assertEquals(Codes::HTTP_OK, $code);
    }
    
    public function testGetAll()
    {
        $client = static::createClient();
        $crawler = $client->request('GET', $this->uri);
        $response = json_decode($client->getResponse()->getContent());
        
        $objectName = $this->controllerPlural;
        $resultCount = count($response->$objectName);
        $this->assertGreaterThan(1, $resultCount);
        
        $code = $client->getResponse()->getStatusCode();
        $this->assertEquals(Codes::HTTP_OK, $code);
    }
    
    public function testPutOne()
    {
        $client = static::createClient();
        $client->request('PUT', $this->uriSingular, $this->putParameters);
        
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
        
        return array('location' => $location, 'id' => $id);
    }
    
    /**
     * @depends testPutOne
     */
    public function testGetOne($putValues)
    {
        $client = static::createClient();
        $client->request('GET', $putValues['location']);
        
        $code = $client->getResponse()->getStatusCode();
        $this->assertEquals(Codes::HTTP_OK, $code);
        
        return $putValues['id'];
    }
    
    /**
     * @depends testGetOne
     */
    public function testPostOne($id)
    {
        $client = static::createClient();
        $client->request('POST', $this->uri . '/' . $id, $this->postParameters);
        $code = $client->getResponse()->getStatusCode();
        $this->assertEquals(Codes::HTTP_NO_CONTENT, $code);
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
}
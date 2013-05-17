<?php
/*
 * This file is part of the BaseApi package.
 *
 * (c) Juan Manuel Torres <kinojman@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Onema\BaseApiBundle\EventListener;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;

use Doctrine\DBAL\DBALException;
use \PDOException;
use \RuntimeException;

use Onema\BaseApiBundle\Event\ApiProcessEvent;

/**
 * @author  Juan Manuel Torres <kinojman@gmail.com>
 */
class RepositoryActionListener
{
    private $parameters;
    private $method;
    
    public function __construct($method, $parameters = array()) {
        $this->method = $method;
        $this->parameters = $parameters;
    }
    
    public function onFindCollection(ApiProcessEvent $event)
    {
        $documents = $this->execute($event);
        $collection = $this->convertDocumentCollection($documents);
        $event->setReturnData($collection);
    }
    
    public function onFindOne(ApiProcessEvent $event)
    {
        $document = $this->execute($event);
        $event->setReturnData($document);
    }
    
    /**
     * JMS Serializer doesn't play well with all doctrine ODM objects. this is a 
     * utility funciton that will put this collection into a siple array. 
     * @param type $documents
     * @return array
     */
    private function convertDocumentCollection($documents)
    {
        $collection = array();
        
        foreach ($documents as $document) {
            $collection[] = $document;
        }
        
        // No data should return a 404 
        if(empty($collection)) {
            throw new ResourceNotFoundException('Could not find resource', 404);
        }
        
        return $collection;
    }
    
    private function execute(ApiProcessEvent $event)
    {
        $repository = $event->getRepository();
        
        try {
            $documents = call_user_func_array(
                array(
                    $repository, 
                    $this->method
                ), $this->parameters);
        }
        catch(DBALException $e) {
            throw new RuntimeException('A DBAL error occurred while processing your request');
        }
        catch (PDOException $e) {
            throw new RuntimeException('A DB configuration error occurred while processing your request');
        }
        
        // No data should return a 404 
        if(empty($documents)) {
            throw new ResourceNotFoundException('Could not find resource', 404);
        }
        
        return $documents;
    }
}
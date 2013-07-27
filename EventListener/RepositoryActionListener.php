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
    private $arguments;
    private $method;
    
    public function __construct($method = null, $arguments = array()) {
        $this->method = $method;
        $this->arguments = $arguments;
    }
    
    /**
     * This method should be called when more than one object is expected. The 
     * collection of data will be moved/converted over to an array. 
     * Doctrine ORM doesn't require this conversion, but Doctrine MongoDB ODM 
     * does.
     * 
     * @param \Onema\BaseApiBundle\Event\ApiProcessEvent $event
     */
    public function onFindCollection(ApiProcessEvent $event)
    {
        $documents = $this->execute($event);
        $collection = $this->convertDocumentCollection($documents);
        $event->setReturnData($collection);
    }
    
    /**
     * This method should be called when a single result is expected. 
     * 
     * @param \Onema\BaseApiBundle\Event\ApiProcessEvent $event
     * @deprecated since version 0.0.2
     */
    public function onFindOne(ApiProcessEvent $event)
    {
        $document = $this->execute($event);
        $event->setReturnData($document);
    }
    
    
    public function onCall(ApiProcessEvent $event)
    {
        $this->method = $event->getMethod();
        $this->arguments = $event->getArguments();
        
        $event->setReturnData($document);
        $document = $this->execute($event);
    }
    
    /**
     * JMS Serializer doesn't play well with doctrine ODM Cursor objects. this is a 
     * utility method that will put this collection into a siple array. 
     * @param type $documents
     * @return array
     */
    private function convertDocumentCollection($documents)
    {
        if($documents instanceof \Doctrine\ODM\MongoDB\Cursor) {
            $documents = $documents->toArray();
        }
        
        // No data should return a 404 
        if(empty($documents)) {
            throw new ResourceNotFoundException('Could not find resource', 404);
        }
        
        return $documents;
    }
    
    /**
     * Uses the repository to execute a query.
     * 
     * @param \Onema\BaseApiBundle\Event\ApiProcessEvent $event
     * @return Entity|Document
     * @throws RuntimeException
     * @throws ResourceNotFoundException
     */
    private function execute(ApiProcessEvent $event)
    {
        $repository = $event->getRepository();
        
        try {
            $documents = call_user_func_array(
                array(
                    $repository, 
                    $this->method
                ), $this->arguments);
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
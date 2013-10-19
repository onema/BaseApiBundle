<?php
/*
 * This file is part of the BaseApi package.
 *
 * (c) Juan Manuel Torres <kinojman@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Onema\BaseApiBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;

use JMS\Serializer\SerializerBuilder;

use FOS\RestBundle\View\View;
use FOS\Rest\Util\Codes;

use Onema\BaseApiBundle\Exception\MissingRepositoryMethodException;
use Onema\BaseApiBundle\Event\ApiProcessEvent;
use Onema\BaseApiBundle\EventListener\RepositoryActionListener;

/**
 * @author  Juan Manuel Torres <kinojman@gmail.com>
 */
class BaseApiController extends Controller 
{
    const VENDOR = 0;
    const BUNDLE = 1;
    const API_GET = 'api.get';
    const API_PROCESS = 'api.process';
    const API_REPOSITORY = 'api.use_repository';
    
    protected $dispatcher;
    protected $defaultRepository;
    protected $defaultDataStore;
    
    public function __construct() 
    {
        $this->dispatcher = new EventDispatcher();
        $repositoryActionListener = new RepositoryActionListener();
        $this->dispatcher->addListener(self::API_REPOSITORY, array($repositoryActionListener, 'onCall'));
    }
    
    /**
     * Adds the Default Repository methods to the controller. This method leverages 
     * the event dispatcher to call any method of the repository defined in 
     * BaseApiController::defaultRepository.
     * 
     * How to extend a Class without Using Inheritance {@link http://symfony.com/doc/current/cookbook/event_dispatcher/class_extension.html}
     * Related classes {@link Onema\BaseApiBundle\EventListener\RepositoryActionListener}
     * and {@link Onema\BaseApiBundle\Event\ApiProcessEvent}
     * 
     * @param string $method
     * @param array $arguments
     * @return mixed
     * @throws \Exception
     * @throws MissingRepositoryMethodException
     */
    public function __call($method, $arguments)
    {
        $repository = $this->getRepository($this->defaultRepository, $this->defaultDataStore);
        
        // The registered event listener from the child class will be called.
        $event = new ApiProcessEvent($repository, $method, $arguments);
        $this->dispatcher->dispatch(self::API_REPOSITORY, $event);

        // no listener was able to process the event? The method does not exist
        if (!$event->isProcessed()) {
            throw new MissingRepositoryMethodException(sprintf('Call to undefined method %s::%s.', get_class($this), $method));
        }

        // return the listener returned value
        return $event->getReturnData();
        
    }
    
    /**
     * Creates a form using a request object and validates it. Upon success the 
     * correct response will be returned. On falure an error message will be 
     * returned. 
     * 
     * @param mixed $document
     * @param string $documentType form type for the entity or document being processed, if none it will be guessed
     * @param string $location string to construct the Location URL
     * @param boolean $isNew
     * @return View FOS\RestBundle\View\View
     */
    protected function processForm($document, $documentType = null, $location = false, $isNew = false)
    {
        $statusCode = $isNew ? Codes::HTTP_CREATED : Codes::HTTP_NO_CONTENT;
        $request = $this->getRequest();
        
        if(!isset($documentType)) {
            // try to guess the document type from the document class name
            $documentTypeClass = $this->getTypeClassName(get_class($document));
            $documentType = new $documentTypeClass();
        }
        
        $form = $this->createForm($documentType, $document, array('method' => $request->getMethod()));
        
        // Support for versions greater than 2.3 which shouldn't pass the request 
        // to the submit method and previous version that support it. 
        if (version_compare(Kernel::VERSION, '2.3', '>=')) {
            $form->handleRequest($this->getRequest());
        }
        else if(version_compare(Kernel::VERSION, '2.1', '>=')){
            $form->submit($this->getRequest());
        }
        
        if($form->isValid()) {
            $manager = $this->getManager();
            $manager->persist($document);
            $manager->flush();
            
            $view = View::create(null, $statusCode);
            
            if($statusCode === Codes::HTTP_CREATED) {
                $view->setHeader('Location',
                    $this->generateUrl(
                        $location, 
                        array('id' => $document->getId()),
                        true // absolute
                    )
                );
            }
        }
        else {
            $errors = $this->getErrorMessages($form);
            $view = View::create($errors, Codes::HTTP_BAD_REQUEST);
        }
        
        return $view;
    }
    
    /**
     * 
     * @param mixed $document document|entity 
     * @param mixed $documentType form type for the entity or document.
     * @param string $location string to construct the Location URL
     * @return View FOS\RestBundle\View\View
     */
    protected function create($document, $documentType, $location)
    {
        return $this->processForm($document, $documentType, $location, true);
    }
    
    /**
     * 
     * @param mixed $id
     * @param mixed $documentType
     * @param string $repositoryName
     * @param string $dataStore
     * @return View FOS\RestBundle\View\View
     */
    protected function edit($id, $documentType = null, $repositoryName = null, $dataStore = null)
    {
        $document = $this->getOne('findOneById', array('id' => $id), $repositoryName, $dataStore);
        
        if($document === null) {
            /**
             * @todo Add support for PUT (idempotent) operations 
             */
            $view = View::create(sprintf('The requested resource with id "%s" doesn\'t exist.', $id), 400);
        }
        else {
            $view = $this->processForm($document, $documentType);
        }
        
        return $view;
    }
    
    /**
     * Delete a document or entity using it's id. 
     * 
     * @param mixed $id
     * @param string $repositoryName
     * @param string $dataStore
     * @return View FOS\RestBundle\View\View
     */
    protected function delete($id, $repositoryName = null, $dataStore = null)
    {
        $document = $this->getOne('findOneById', array('id' => $id), $repositoryName, $dataStore);
        
        $manager = $this->getManager();
        $manager->remove($document);
        $manager->flush();

        return View::create(null, Codes::HTTP_NO_CONTENT);
    }
    
    /**
     * Returns the data requested by the child controller.
     * 
     * @param string $repositoryName Name of the Entity/Document repository
     * @param string $dataStore either doctrine or doctrine_mongodb
     * @return mixed data requested by the child controller
     */
    protected function processData($repositoryName = null, $dataStore = null)
    {
        $repository = $this->getRepository($repositoryName, $dataStore);
        
        // The registered event listener from the child class will be called.
        if($this->dispatcher->hasListeners(self::API_GET)) {
            $event = new ApiProcessEvent($repository);
            $this->dispatcher->dispatch(self::API_GET, $event);

            $data = $event->getReturnData();
        }
        else if($this->dispatcher->hasListeners(self::API_PROCESS)) {
            /**
             * Not implemented yet...
             * @TODO add listener to perform actions other than search. consider using 
             * the form listeners to avoid this block all together. 
             */
            $data = array();
        }

        return $data;
    }
    
    /**
     * Uses the default API_GET listener to call one of three repository methods:
     *  - findById
     *  - findAll (It is preferred to use findPaginated)
     *  - findPaginated (must be implemented in each repository)  
     * 
     * @param string $method
     * @param array $parameters
     * @param string $repositoryName
     * @param string $dataStore either doctrine OR doctrine_mongodb
     * @return type
     */
    protected function getOne($method, $parameters = array(), $repositoryName = null, $dataStore = null)
    {
        $listener = new RepositoryActionListener($method, $parameters);
        $this->dispatcher->addListener(self::API_GET, array($listener, 'onFindOne'));
        
        $data = $this->processData($repositoryName, $dataStore);
        $this->dispatcher->removeListener(self::API_GET, $listener);
        
        return $data;
    }
    
    /**
     * Calls the given repository method through the ActionListener method onFindCollection.
     * Use this when multiple results are expected. if a single result is found, it 
     * will be contained in an array.
     * 
     * @param string $method repository method to be called
     * @param array $parameters list of parameters to pass to the repository method. 
     * @param string $repo repository name
     * @param string $dataStore either doctrine or doctrine_mongodb
     * @return mixed Document
     */
    protected function getCollection($method, $parameters = array(), $repositoryName = null, $dataStore = null)
    {
        $listener = new RepositoryActionListener($method, $parameters);
        $this->dispatcher->addListener(self::API_GET, array($listener, 'onFindCollection'));
        
        $data = $this->processData($repositoryName, $dataStore);
        $this->dispatcher->removeListener(self::API_GET, array($listener, 'onFindCollection'));
        
        return $data;
    }
    
    /**
     * 
     * @param string $method
     * @param array $parameters
     * @param string $repositoryName
     * @param string $dataStore
     * @return type
     */
    protected function postUpdate($method, $parameters = array(), $repositoryName = null, $dataStore = null)
    {
        $listener = new RepositoryActionListener($method, $parameters);
        $this->dispatcher->addListener(self::API_PROCESS, array($listener, $method));
        
        return $this->processData($repositoryName, $dataStore);
    }
    
    /**
     * Get the pagination parameters form the query:
     * - skip: integer, where it should start getting parameters (skip for mongo queries)
     * - limit: maximum nubmer of results it should return. 
     * 
     * @return array
     */
    protected function getPagination()
    {
        $query = $this->getRequest()->query;
        return array(
            'skip' => $query->get('skip'), 
            'limit'   => $query->get('limit')
        );
    }

    /**
     * Get a doctrine repository bassed on the repo name and the type of data 
     * store ie MongoDB or ORM
     * @param string $repositoryName Name of the Entity/Document repository
     * @param string $dataStore either doctrine or doctrine_mongdb
     * @return type
     */
    protected function getRepository($repositoryName = null, $dataStore = null)
    {
        if(!isset($repositoryName)) {
            $repositoryName = $this->defaultRepository;
        }
        
        $manager = $this->getManager($dataStore);
        return $manager->getRepository($repositoryName);
    }
   
    /**
     * Returns the appropriate doctrine manager given a data store. 
     * Current options include: 
     * - 'doctrine'
     * - 'doctrine_mongodb'
     * 
     * @param string|null $dataStore name of the data manager that will be used
     * @return type Doctrine object or document manager
     */
    protected function getManager($dataStore = null)
    {
        if(!isset($dataStore)) {
            $dataStore = $this->defaultDataStore;
        }
        
        return $this->container->get($dataStore)->getManager();
    }
    
    /**
     * Try to guess the form type class namespace and name based on the class name.
     * @param string $documentClass
     * @return type
     */
    private function getTypeClassName($documentClass)
    {
        $parts = explode('\\', $documentClass);
        $size = sizeof($parts);
        
        return $parts[self::VENDOR].'\\'.$parts[self::BUNDLE].'\\Form\\Type\\'.$parts[$size-1] . 'Type';
    }
    
    /**
     * Generate a simpler validation error structure.
     * 
     * @param \Symfony\Component\Form\Form $form
     * @return type
     */
    private function getErrorMessages(\Symfony\Component\Form\Form $form) {
        
        $errors = array();
        
        foreach ($form->getErrors() as $key => $error) {
            $errors[$key] = $error->getMessage();
        }
        
        if ($form->count()) {
            foreach ($form->all() as $child) {
                if (!$child->isValid()) {
                    $errors[$child->getName()] = $this->getErrorMessages($child);
                }
            }
        }
        
        return $errors;
    }
}

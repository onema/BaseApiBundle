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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

use FOS\RestBundle\View\View;
use FOS\Rest\Util\Codes;

use Onema\BaseApiBundle\Event\ApiProcessEvent;
use Onema\BaseApiBundle\EventListener\RepositoryActionListener;

/**
 * @author  Juan Manuel Torres <kinojman@gmail.com>
 */
class BaseRestController extends Controller 
{
    const VENDOR = 0;
    const BUNDLE = 1;
    const API_GET = 'api.get';
    const API_PROCESS = 'api.process';
    
    protected $dispatcher;
    protected $defaultRepository;
    protected $defaultDataStore;
    
    public function __construct() {
        $this->dispatcher = new EventDispatcher();
    }
    
    /**
     * Creates a form using a request object and validates it. Upon success the 
     * correct response will be returned. On falure an error message will be 
     * returned. 
     * 
     *  // register custom action listeners
     *  $listener = new CustomActionListener();
     *  $this->dispatcher->addListener(parent::GET, array($listener, 'onCustomListenerMethod'));
     *  $documents = $this->findData();
     * 
     * @param mixed $document
     * @param string $location string to construct the Location URL
     * @param boolean $isNew
     * @return mixed Symfony\Component\HttpFoundation\Response or View
     */
    protected function processForm($document, $documentType = null, $location = false, $isNew = false)
    {
        $statusCode = $isNew ? Codes::HTTP_CREATED : Codes::HTTP_NO_CONTENT;
        
        if(!isset($documentType)) {
            // try to guess the document type from the document class type
            $documentType = $this->getTypeClassName(get_class($document));
        }
        
        $form = $this->createForm($documentType, $document);
        $form->bind($this->getRequest());
        
        if($form->isValid()) {
            $manager = $this->getManager();
            $manager->persist($document);
            $manager->flush();
            
            $response = new Response();
            $response->setStatusCode($statusCode);
            
            if($statusCode === Codes::HTTP_CREATED) {
                $response->headers->set('Location', 
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
            $response = View::create($errors, Codes::HTTP_BAD_REQUEST);
        }
        
        return $response;
    }
    
    protected function create($document, $documentType, $location)
    {
        return $this->processForm($document, $documentType, $location, true);
    }
    
    /**
     * 
     * @param mixed $id 
     * @param string $repoName name of the repository asociated with the document/entity that will be modified.
     * @param string $location use to create the Location URL
     * @return mixed Symfony\Component\HttpFoundation\Response or View
     */
    protected function edit($id, $documentType, $repositoryName = null, $dataStore = null)
    {
        $document = $this->getOne('findOneById', array('id' => $id), $repositoryName, $dataStore);
        
        if($document === null) {
            $response = View::create(sprintf('The requested resource with id "%s" doesn\'t exist.', $id), 400);
        }
        else {
            $response = $this->processForm($document, $documentType);
        }
        
        return $response;
    }
    
    protected function delete($id, $repositoryName = null, $dataStore = null)
    {
        $document = $this->getOne('findOneById', array('id' => $id), $repositoryName, $dataStore);
        
        if($document === null) {
            $response = View::create(sprintf('The resource with id "%s" doesn\'t exist.', $id), 400);
        }
        else {
            $manager = $this->getManager();
            $manager->remove($document);
            $manager->flush();
            
            $response = new Response();
            $response->setStatusCode(Codes::HTTP_NO_CONTENT);
        }
        
        return $response;
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
        
        return $this->processData($repositoryName, $dataStore);
    }
    
    /**
     * Calls the given repository method through the ActionListener method onFindCollection.
     * Use this when multiple results are expected. if a single result is found, it 
     * will be contained in an array.
     * 
     * @param string $repo repository name
     * @param string $method repository method to be called
     * @param array $parameters list of parameters to pass to the repository method. 
     * @return mixed Document
     */
    protected function getCollection($method, $parameters = array(), $repositoryName = null, $dataStore = null)
    {
        $listener = new RepositoryActionListener($method, $parameters);
        $this->dispatcher->addListener(self::API_GET, array($listener, 'onFindCollection'));
        
        return $this->processData($repositoryName, $dataStore);
    }
    
    /**
     * 
     * @param type $method
     * @param type $parameters
     * @param type $repositoryName
     * @param type $dataStore
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
     * - from: integer, where it should start getting parameters (skip for mongo queries)
     * - limit: maximum nubmer of results it should return. 
     * 
     * @return array
     */
    protected function getPagination()
    {
        $query = $this->getRequest()->query;
        return array(
            'from' => $query->get('from'), 
            'limit'   => $query->get('limit')
        );
    }

    /**
     * Get a doctrine repository bassed on the repo name and the type of data 
     * store ie MongoDB or ORM
     * @param string $repositoryName Name of the Entity/Document repository
     * @param type $dataStore either doctrine or doctrine_mongdb
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
     * @param string|null
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
     * Get the class "type" including the namespace that corresponds to the given document. 
     * This is a simple 1:1 between a Document and it's DocumentType
     * @param type $documentClass
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
        
        if ($form->hasChildren()) {
            foreach ($form->getChildren() as $child) {
                if (!$child->isValid()) {
                    $errors[$child->getName()] = $this->getErrorMessages($child);
                }
            }
        }
        
        return $errors;
    }
}

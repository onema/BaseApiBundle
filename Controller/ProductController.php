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

//Use annotations for our FOSRest config
use Symfony\Component\HttpFoundation\Request;
use FOS\RestBundle\Controller\Annotations as Rest;   
use FOS\RestBundle\Routing\ClassResourceInterface;   
use FOS\Rest\Util\Codes;

use Onema\BaseApiBundle\EventListener\RepositoryActionListener;
use Onema\BaseApiBundle\Entity\Product;
use Onema\BaseApiBundle\Form\Type\ProductType;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;

/**
 * Prototype controller to test base api controller.
 * 
 * @package Onema\BaseApiBundle\Controller
 */
class ProductController extends BaseApiController implements ClassResourceInterface
{
    public function __construct() {
        parent::__construct();
        $this->defaultRepository = 'BaseApiBundle:Product';
        $this->defaultDataStore = 'doctrine';
    }
    
    /**
     * Prototype put action
     * 
     * 
     * @ApiDoc(
     *  resource=true,
     *  description="Create a new Object",
     *  input="Onema\BaseApiBundle\Form\Type\ProductType",
     *  output="",
     *  statusCodes={
     *         201="Returned when successful",
     *         403="Returned when the user is not authorized",
     *         400={
     *           "Returned when an error occurred",
     *           "Returned when validation didn't pass"
     *         }
     *     }
     * )
     * @return type
     */
    public function postAction()
    {
        return $this->create(new Product(), new ProductType(), 'get_product');
    }
    
    /**
     * @ApiDoc(
     *  description="Update Object",
     *  input={
     *      "class"="Onema\BaseApiBundle\Form\Type\ProductType",
     *      "groups"={"update"}
     *  },
     *  output="Your\Namespace\Class",
     *  statusCodes={
     *         204="Returned when successful",
     *         403="Returned when the user is not authorized to say hello",
     *         404={
     *           "Returned when the user is not found",
     *           "Returned when somehting else is not found"
     *         }
     *     }
     * )
     * @param integer $id unique identifier of the resource
     * @return Response
     */
    public function putAction($id)
    {
        return $this->edit($id, new ProductType());
    }
    
    /**
     * Delete a resource identified by the request id. 
     * 
     * @ApiDoc(
     *  description="Delete a resource identified by {id}",
     *  statusCodes={
     *      204="Returned when successful",
     *      403="Returned when the user is not authorized to create the given resource",
     *      404="Returned when the resource was not found",
     *      500="Returned when a server error occurred"
     *     }
     * )
     * @param integer $id the id of the article to be deleted
     * @return type
     */
    public function deleteAction($id)
    {
        return $this->delete($id);
    }

    /**
     * Returns a collection of entities, pagination is supported for this method.
     * Attach custom event listeners like this:
     * ```
     * $listener = new CustomActionListener($method, $parameters);
     * $this->dispatcher->addListener(parent::API_GET, array($listener, 'onFindOne'));
     * $products = $this->processData('BaseApiBundle:Product', 'doctrine_mongodb');
     * ```
     * 
     * Create a custom serialized response like this:
     * ```
     * $serializer = $this->container->get('serializer');
     * $response = $serializer->serialize(array('article' => $article), $_format, SerializationContext::create()->setGroups(array('all')));
     * return new Response($response);
     * ```
     * @ApiDoc(
     *  description="PUT method to create a product",
     *  output={
     *      "class"="Onema\BaseApiBundle\Entity\Product",
     *      "groups"={"public"}
     *  }
     * )
     * @return 
     * @Rest\View(serializerGroups={"public"})
     */
    public function cgetAction()
    {
        $parameters = $this->getPagination();
        $products = $this->getCollection('findPaginated', $parameters);
        
        return array(
            'products' => $products,
        );
    }
    
    /**
     * Returns an entity using it's ID
     * @ApiDoc(
     *  description="get a resource by id",
     *  output={
     *      "class"="Onema\BaseApiBundle\Entity\Product",
     *      "groups"={"public"}
     *  }
     * )
     * @param type $name
     * @return type
     * @Rest\View(serializerGroups={"public"})
     */
    public function getAction($id)
    {
        $products = $this->getOne('findOneById', array('id' => $id));
        return array(
            'product' => $products,
        );
    }
    
    /**
     * This method is an example on how a custom listener can be used to query data.
     * Normally you woulnd't need a custom listener because you can call any 
     * repository method or custom method using getOne or getCollection. A custom
     * listener could be used for actions that are not related to a database like
     * using a third party API, sending and email, adding a job to a worker queue etc. 
     * 
     * This method also returns a partial response.
     * 
     * @ApiDoc(
     *  description="get a resource by id",
     *  output={
     *      "class"="Onema\BaseApiBundle\Entity\Product",
     *      "groups"={"date"}
     *  }
     * )
     * @Rest\View(serializerGroups={"date"})
     * @see Onema\BaseApiBundle\EventListener\ResponseActionListener 
     */
    public function dateAction()
    {
        $parameters = $this->getPagination();

        $listener = new RepositoryActionListener('findPaginated', $parameters);
        $this->dispatcher->addListener(parent::API_GET, array($listener, 'onFindCollection'));

        $products = $this->processData('BaseApiBundle:Product', 'doctrine');

        return array(
            'products' => $products,
        );
    }
}

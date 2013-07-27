<?php
/*
 * This file is part of the BaseApi package.
 *
 * (c) Juan Manuel Torres <kinojman@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Onema\BaseApiBundle\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * @author  Juan Manuel Torres <kinojman@gmail.com>
 */
class ApiProcessEvent extends Event
{
    protected $repository;
    protected $data;
    protected $isProcessed = false; 
    protected $method;
    protected $arguments;

    public function __construct($repository, $method, $arguments) 
    {
        $this->repository = $repository;
        $this->method = $method;
        $this->arguments = $arguments;
    }
    
    public function getRepository()
    {
        return $this->repository;
    }
    
    public function getMethod() 
    {
        return $this->method;
    }
    
    public function getArguments()
    {
        return $this->arguments;
    }
    
    /**
     * Sets the value to return and stops other listeners from being notified
     */
    public function setReturnData($data)
    {
        $this->data = $data;
        $this->isProcessed = true;
        $this->stopPropagation();
    }
    
    public function getReturnData()
    {
        return $this->data;
    }
    
    public function isProcessed()
    {
        return $this->isProcessed;
    }
}
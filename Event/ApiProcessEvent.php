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
    
    public function __construct($repository) 
    {
        $this->repository = $repository;
    }
    
    public function getRepository()
    {
        return $this->repository;
    }
    
    public function setReturnData($data)
    {
        $this->data = $data;
    }
    
    public function getReturnData()
    {
        return $this->data;
    }
}
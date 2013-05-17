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

/**
 * @author  Juan Manuel Torres <kinojman@gmail.com>
 */
class ProductControllerTest extends BaseCases
{
    protected $controllerPlural;
    protected $controllerSingular;

    public function setUp()
    {
        $this->controllerPlural = 'products';
        $this->controllerSingular = 'product';
        
        $this->putParameters = array(
            'product' => array(
                'name' => 'testeo',
                'images' => array(array('path'=>'image1.png'), array('path' => 'image2.png')),
                'category' => 'category 1',
            )
        );
        
        $this->postParameters = array(
            'product' => array(
                'name' => 'testeo' . time() ,
                'images' => array(array('path'=>'image3.png'), array('path' => 'image4.png')),
                'yelp' => 'someyelpsite.com',
                'category' => 'category 2',
            )
        );
        
        parent::setUp();
    }
}
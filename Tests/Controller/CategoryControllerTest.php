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

class CategoryControllerTest extends BaseCases
{
    protected $controllerPlural;
    protected $controllerSingular;

    public function setUp()
    {
        $this->controllerPlural = 'categories';
        $this->controllerSingular = 'category';
        
        $this->putParameters = array(
            'category' => array(
                'name' => 'category test',
            )
        );
        
        $this->postParameters = array(
            'category' => array(
                'name' => 'category test' . time() ,
            )
        );
        
        parent::setUp();
    }
}
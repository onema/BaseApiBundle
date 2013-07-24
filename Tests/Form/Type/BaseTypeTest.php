<?php

/*
 *  This file is part of the Onema\BaseApiBundle.
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */
namespace Onema\BaseApiBundle\Tests\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Test\TypeTestCase;
/**
 * Description of BaseTypeTest
 * 
 * @author  Juan Manuel Torres <kinojmana@gmail.com> 
 */
abstract class BaseTypeTest extends TypeTestCase 
{
    public function submitValidData(array $formData, AbstractType $type) 
    {
        $form = $this->factory->create($type);
        $document = $this->fromArray($formData);
        
        // submit the data to the form directly
        $form->submit($formData);
        
        $this->assertTrue($form->isSynchronized());
        $this->assertEquals($document, $form->getData());

        $view = $form->createView();
        $children = $view->children;

        foreach (array_keys($formData) as $key) {
            $this->assertArrayHasKey($key, $children);
        }
    }
    
    public function submitInvalidData(array $formData, AbstractType $type)
    {
        $form = $this->factory->create($type);
//        $document = $this->fromArray($formData);
//        
//        // submit the data to the form directly
//        $form->submit($formData);
//        
//        $this->assertTrue(!$form->isSynchronized());
//        $this->assertNotEquals($document, $form->getData());

    }
    
    /**
     * Return an array of data containing valid data. 
     * @return array return an array of data with the format specified in the link below
     * @link http://symfony.com/doc/current/cookbook/form/unit_testing.html#testing-against-different-sets-of-data docs to test multiple sets of data
     */
    public abstract function getValidTestData();
    
    /**
     * Return an array of invalid data. 
     * @return array array with invalid data, it must use the format specified in the link below
     * @link http://symfony.com/doc/current/cookbook/form/unit_testing.html#testing-against-different-sets-of-data docs to test multiple sets of data
     */
    public abstract function getInvalidTestData();  
    
    /**
     * Construct an object (model) from the submitted data
     * @return object model object
     */
    public abstract function fromArray(array $formData);
}


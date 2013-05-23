<?php
/*
 * This file is part of the BaseApi package.
 *
 * (c) Juan Manuel Torres <kinojman@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Onema\BaseApiBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

use Symfony\Component\Validator\Constraints;
use JMS\Serializer\Annotation\ExclusionPolicy;  //Ver 0.11+ the namespace has changed from JMS\SerializerBundle\* to JMS\Serializer\*
use JMS\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="Onema\BaseApiBundle\Repository\ProductRepository")
 * @ORM\Table(name="product")
 * @ORM\HasLifecycleCallbacks()
 */
class Product 
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     * @Groups({"public", "date"})
     */
    protected $id;
    
    /**
     * @ORM\Column(type="string", length=100)
     * @Groups({"public", "date"})
     */
    protected $name;

    /**
     * @ORM\Column(type="decimal", scale=2)
     * @Groups({"public"})
     */
    protected $price;
    
    /**
     * @ORM\Column(type="text")
     * @Groups({"public"})
     */
    protected $description;
    
    /**
     * @ORM\Column(type="datetime") 
     * @Groups({"date"})
     */
    protected $created;
    

    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
        $this->created = new \DateTime();
    }

    /**
     * Set name
     *
     * @param string $name
     * @return Product
     */
    public function setName($name)
    {
        $this->name = $name;
    
        return $this;
    }

    /**
     * Get name
     *
     * @return string 
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set price
     *
     * @param float $price
     * @return Product
     */
    public function setPrice($price)
    {
        $this->price = $price;
    
        return $this;
    }

    /**
     * Get price
     *
     * @return float 
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * Set description
     *
     * @param string $description
     * @return Product
     */
    public function setDescription($description)
    {
        $this->description = $description;
    
        return $this;
    }

    /**
     * Get description
     *
     * @return string 
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set category
     *
     * @param \Onema\StoreBundle\Entity\Category $category
     * @return Product
     */
    public function setCategory($category = null)
    {
        $this->category = $category;
    
        return $this;
    }

    /**
     * Get category
     *
     * @return \Onema\StoreBundle\Entity\Category 
     */
    public function getCategory()
    {
        return $this->category;
    }
    
    /**
     * @ORM\PrePersist
     */
    public function setCreatedValue()
    {
        $this->created = new \DateTime();
    }

    /**
     * Set created
     *
     * @param \DateTime $created
     * @return Product
     */
    public function setCreated($created)
    {
        $this->created = $created;
    
        return $this;
    }

    /**
     * Get created
     *
     * @return \DateTime 
     */
    public function getCreated()
    {
        return $this->created;
    }
}

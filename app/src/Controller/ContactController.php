<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @author Yuu2
 */
class ContactController extends AbstractController implements ApplicationController {

    /**
     * @access public
     */
    public function __construct() {}

    /**
     * 입력 폼
     * @access public
     * @Route("/contact/form", name="contact", methods={"GET"})
     * @Template("/contact/contact.twig")
     */
    public function contact() {
      
        return array();
    }

    /** 
     * 입력 최종 확인
     * @access public
     * @Route("/contact/confirm", name="contact_confirm", methods={"POST"})
     * @Template("/contact/confirm.twig")
     */
    public function confirm() {

        return array();
    }

    /** 
     * 전송완료
     *  
     * @access public
     * 
     * @Route("/contact/confirm", name="contact_complete", methods={"POST"})
     * @Template("/contact/confirm.twig")
     */
    public function complete() {
        
        return array();
    }
    
}
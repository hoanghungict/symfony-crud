<?php
namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

class ProductController extends Controller
{
    /**
     * @Route("/products")
     */
    public function index()
    {
        $number = mt_rand(0, 100);

        return $this->render('products/index.html.twig', array(
            'number' => $number,
        ));

    }
}

?>
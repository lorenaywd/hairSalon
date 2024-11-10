<?php

namespace App\Controller;

use App\Repository\ServiceRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ServiceController extends AbstractController
{

#[Route('/services', name: 'app_services')]
public function index(ServiceRepository $serviceRepository): Response
{
    $services = $serviceRepository->findAll();
    return $this->render('service/index.html.twig', [
        'services' => $services,
    ]);
}

}


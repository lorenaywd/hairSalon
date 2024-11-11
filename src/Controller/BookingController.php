<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Entity\Booking;
use App\Entity\Service;
use App\Repository\BookingRepository;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class BookingController extends AbstractController
{
    private $serviceRepository;
    private $entityManager;

    public function __construct(ServiceRepository $serviceRepository, EntityManagerInterface $entityManager)
    {
        $this->serviceRepository = $serviceRepository;
        $this->entityManager = $entityManager;
    }

    #[Route('/booking/{serviceId<\d+>}', name: 'app_booking')]
    public function index(Request $request, int $serviceId): Response
    {
       

        $service = $this->serviceRepository->find($serviceId);
        if (!$service) {
            throw $this->createNotFoundException('Service introuvable');
        }

        // Initialisation des créneaux horaires pour chaque jour
        $date = new \DateTime('tomorrow'); 
        $times = ['09:00', '11:00', '14:00', '15:00', '16:00'];
        $weeklySlots = []; // Tableau pour stocker les créneaux sur une semaine

        // Génération de créneaux pour les 6 prochains jours
        for ($i = 0; $i < 6; $i++) {
            $currentDate = clone $date;
            $currentDate->modify("+$i day"); // Ajouter un jour par itération
            $daySlots = [];

            foreach ($times as $time) {
                $dateTime = clone $currentDate;
                $dateTime->setTime((int)explode(':', $time)[0], (int)explode(':', $time)[1]);

                // Vérification de la réservation du crénaux
                $existingBooking = $this->entityManager->getRepository(Booking::class)
                    ->findOneBy(['service' => $service, 'appointmentDate' => $dateTime]);

    
                if (!$existingBooking) {
                    $daySlots[] = $dateTime->format('Y-m-d H:i:s');
                }
            }


            if (!empty($daySlots)) {
                $weeklySlots[] = [
                    'date' => $currentDate->format('Y-m-d'), 
                    'slots' => $daySlots
                ];
            }
        }

        // form de réservation
        $form = $this->createForm(UserType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Récupérer l'utilisateur et le créneau sélectionné
            $user = $form->getData();
            $slotId = $request->get('slotId');
            $slotToReserve = null;

            // Vérifier si l'utilisateur existe déjà dans la base de données
            $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $user->getEmail()]);

            if ($existingUser) {
                $user = $existingUser;
            } else {
                $this->entityManager->persist($user);
            }

            // Vérifier que l'utilisateur a sélectionné un créneau
            if ($slotId) {
                // Vérifier si le créneau existe dans les créneaux disponibles
                foreach ($weeklySlots as $weekDay) {
                    foreach ($weekDay['slots'] as $slot) {
                        if ($slot === $slotId) {
                            $slotToReserve = \DateTime::createFromFormat('Y-m-d H:i:s', $slot);
                            break 2;
                        }
                    }
                }

                // Vérifier si le créneau est déjà réservé
                $existingBooking = $this->entityManager->getRepository(Booking::class)
                    ->findOneBy(['service' => $service, 'appointmentDate' => $slotToReserve]);

                if ($existingBooking) {
                    $this->addFlash('error', 'Le créneau sélectionné est déjà réservé.');
                } else {
                    // Je crée et sauvegarde la réservation
                    $booking = new Booking();
                    $booking->setUser($user)
                        ->setService($service)
                        ->setAppointmentDate($slotToReserve)
                        ->setTime($slotToReserve);

                    $this->entityManager->persist($booking);
                    $this->entityManager->flush();

    
                    return $this->redirectToRoute('app_booking_confirmation');
    
                }
            } else {
                $this->addFlash('error', 'Veuillez sélectionner un créneau avant de confirmer la réservation.');
            }
        }

        return $this->render('booking/index.html.twig', [
            'service' => $service,
            'weeklySlots' => $weeklySlots,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/booking/confirmation', name: 'app_booking_confirmation')]
    public function ConfirmeReservation(): Response
    {
        return $this->render('booking/confirmation.html.twig');
    }


}



<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\Room;
use App\Form\ReservationType;
use App\Repository\ReservationRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Workflow\Registry;

/**
 * @Route("/reservation", name="reservation_")
 */
class ReservationController extends Controller
{
    /**
     * @Route("/", name="index", methods="GET")
     * @IsGranted("ROLE_SECRETARY", statusCode=403, message="Accès Refusé!Vos droits ne sont pas suffisant !")
     *
     * @param ReservationRepository $reservationRepository
     *
     * @return Response
     */
    public function index(ReservationRepository $reservationRepository): Response
    {
        return $this->render('reservation/index.html.twig', ['reservations' => $reservationRepository->findInProgress()]);
    }

    /**
     * @Route("/mes-reservations", name="mes_reservations", methods={"GET"})
     * @IsGranted("ROLE_UTILISATEUR" , statusCode=403, message="Accès Refusé! Vos droits ne sont pas suffisant !")
     */
    public function mesReservations()
    {
        $reservations = $this
            ->getDoctrine()
            ->getRepository(Reservation::class)
            ->findInProgressUser($this->getUser());
        //->findBy(['user' => $this->getUser()]);

        return $this->render('reservation/mes-reservations.html.twig', [
            'reservations' => $reservations,
        ]);
    }

    /**
     * @Route("/{id}/new/{date}", name="new", methods="GET|POST")
     * @IsGranted("ROLE_CAN_DO_BOOKING" , statusCode=403, message="Accès Refusé! Vos droits ne sont pas suffisant !")
     *
     * @param Request  $request
     * @param Registry $workflows
     * @param Room     $room
     * @param $date
     *
     * @return Response
     */
    public function new(Request $request, Registry $workflows, Room $room, $date): Response
    {
        $reservation = new Reservation();
        $form = $this->createForm(ReservationType::class, $reservation);
        $form->add('date', HiddenType::class, array(
            'data' => $date,
        ));
        $form->handleRequest($request);

        $workflow = $workflows->get($reservation);

        if ($form->isSubmitted() && $form->isValid()) {
            $dateFormated = new \DateTime($reservation->getDate());
            $startFormated = new \DateTime($reservation->getDate().$reservation->getStart());
            $endFormated = new \DateTime($reservation->getDate().$reservation->getEnd());

            $reservation->setDate($dateFormated);
            $reservation->setRoom($room);
            $reservation->setUser($this->getUser());
            $reservation->setState('created');
            $reservation->setStart($startFormated);
            $reservation->setEnd($endFormated);

            $em = $this->getDoctrine()->getManager();
            $em->persist($reservation);
            $em->flush();

            return $this->redirectToRoute('room_calendar_show', array('id' => $room->getId()));
        }

        return $this->render('reservation/new.html.twig', [
            'date' => $date,
            'reservation' => $reservation,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="show", methods="GET")
     * @Security("is_granted('view', reservation) or has_role('ROLE_SECRETARY')")
     *
     * @param Reservation $reservation
     *
     * @return Response
     */
    public function show(Reservation $reservation): Response
    {
        return $this->render('reservation/show.html.twig', ['reservation' => $reservation]);
    }

    /**
     * @Route("/{id}/edit", name="edit", methods="GET|POST")
     * @Security("is_granted('view', reservation) or has_role('ROLE_SECRETARY')")
     *
     * @param Request     $request
     * @param Reservation $reservation
     *
     * @return Response
     */
    public function edit(Request $request, Reservation $reservation): Response
    {
        $start = $reservation->getStart();
        $end = $reservation->getEnd();

        $reservation->setStart($start->format('Y-m-d H:i'));
        $reservation->setEnd($end->format('Y-m-d H:i'));

        $form = $this->createForm(ReservationType::class, $reservation);
        $form->add('date', HiddenType::class, array(
            'data' => $reservation->getDate()->format('Y-m-d'),
        ));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $dateFormated = new \DateTime($reservation->getDate());
            $startFormated = new \DateTime($reservation->getStart());
            $endFormated = new \DateTime($reservation->getEnd());

            $reservation->setStart($startFormated);
            $reservation->setEnd($endFormated);
            $reservation->setDate($dateFormated);

            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('reservation_edit', ['id' => $reservation->getId()]);
        }

        return $this->render('reservation/edit.html.twig', [
            'reservation' => $reservation,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="delete", methods="DELETE")
     * @Security("is_granted('view', reservation) or has_role('ROLE_SECRETARY')")
     *
     * @param Request     $request
     * @param Reservation $reservation
     *
     * @return Response
     */
    public function delete(Request $request, Reservation $reservation): Response
    {
        if ($this->isCsrfTokenValid('delete'.$reservation->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($reservation);
            $em->flush();
        }

        return $this->redirectToRoute('reservation_index');
    }
}

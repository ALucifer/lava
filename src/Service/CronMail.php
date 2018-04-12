<?php
/**
 * Created by PhpStorm.
 * User: coubardalexis
 * Date: 09/04/2018
 * Time: 23:03
 */

namespace App\Service;

use App\Entity\User;
use Twig_Environment;
use Symfony\Bundle\FrameworkBundle\Routing\Router;

class CronMail
{
    protected $mailer;
    protected $templating;

    /**
     * WorkflowMail constructor.
     *
     * @param \Swift_Mailer    $mailer
     * @param Twig_Environment $templating
     */
    public function __construct(\Swift_Mailer $mailer, \Twig_Environment $templating)
    {
        $this->mailer = $mailer;
        $this->templating = $templating;
    }

    /**
     * Email
     *
     * @param $reservations
     * @param User $secretaire
     *
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function mailWarning($reservations,User $secretaire)
    {
        $template = 'email/cronWarningReservation.html.twig';

        $from = 'reservation@lava.com';

        $to = $secretaire->getEmail();

        $subject = 'Reservation non traitée';

        $body = $this->templating->render($template, [
            'reservations' => $reservations,
            'subject' => $subject,
            'user' => $secretaire
        ]);

        $this->sendMessage($from, $to, $subject, $body);
    }

    protected function sendMessage($from, $to, $subject, $body)
    {
        $mail = (new \Swift_Message());

        $mail
            ->setFrom($from)
            ->setTo($to)
            ->setSubject($subject)
            ->setBody($body)
            ->setContentType('text/html');

        $this->mailer->send($mail);
    }
}
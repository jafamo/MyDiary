<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Reminder;
use App\Form\ReminderType;
use App\Service\DateRange;
use App\Service\RecordatoriosService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

class RecordatoriosController
{
    public function __construct(
        private readonly RecordatoriosService $recordatoriosService,
        private readonly FormFactoryInterface $formFactory,
        private readonly EntityManagerInterface $entityManager,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly Environment $twig,
    ) {
    }

    #[Route('/recordatorios', name: 'app_recordatorios', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $now = DateRange::nowInMadrid();
        $today = $now->setTime(0, 0, 0);
        $year = (int) $request->query->get('year', $now->format('Y'));
        $month = (int) $request->query->get('month', $now->format('n'));

        $selectedDay = $this->recordatoriosService->selectedDay($request->query->get('date'));
        $upcoming = $this->recordatoriosService->upcoming($today, (int) $request->query->get('page', 1));
        $history = $this->recordatoriosService->history($today, (int) $request->query->get('history_page', 1));

        return new Response($this->twig->render('recordatorios/index.html.twig', [
            ...$this->recordatoriosService->month($now, $year, $month),
            ...$selectedDay,
            ...$upcoming,
            ...$history,
            ...$this->recordatoriosService->next($today),
            'new_reminder_date' => ($selectedDay['selected_date'] ?? $today)->format('Y-m-d'),
            'total_reminders' => $upcoming['upcoming_total'] + $history['history_total'],
        ]));
    }

    #[Route('/recordatorios', name: 'app_recordatorios_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $reminder = new Reminder();
        $form = $this->formFactory->create(ReminderType::class, $reminder);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid() || '' === trim($reminder->getText())) {
            return new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->entityManager->persist($reminder);
        $this->entityManager->flush();

        return new RedirectResponse($request->headers->get('Referer', '/recordatorios'));
    }

    #[Route('/recordatorios/{reminder}/editar', name: 'app_recordatorios_edit', methods: ['POST'])]
    public function edit(Reminder $reminder, Request $request): Response
    {
        $form = $this->formFactory->create(ReminderType::class, $reminder);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid() || '' === trim($reminder->getText())) {
            return new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $reminder->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return new RedirectResponse($request->headers->get('Referer', '/recordatorios'));
    }

    #[Route('/recordatorios/{reminder}/eliminar', name: 'app_recordatorios_delete', methods: ['POST'])]
    public function delete(Reminder $reminder, Request $request): Response
    {
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('reminder_delete', (string) $request->request->get('_token')))) {
            return new Response(status: Response::HTTP_FORBIDDEN);
        }

        $this->entityManager->remove($reminder);
        $this->entityManager->flush();

        return new RedirectResponse($request->headers->get('Referer', '/recordatorios'));
    }
}

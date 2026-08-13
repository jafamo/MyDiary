<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Reminder;
use App\Form\ReminderType;
use App\Repository\ReminderRepository;
use App\Service\DateRange;
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
    private const UPCOMING_PAGE_SIZE = 20;
    private const HISTORY_PAGE_SIZE = 20;

    public function __construct(
        private readonly ReminderRepository $reminderRepository,
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
        $year = (int) $request->query->get('year', $now->format('Y'));
        $month = (int) $request->query->get('month', $now->format('n'));

        $firstOfMonth = (new \DateTimeImmutable())->setTimezone(new \DateTimeZone('Europe/Madrid'))->setDate($year, $month, 1)->setTime(0, 0, 0);
        $lastOfMonth = $firstOfMonth->modify('last day of this month');

        $leadingDays = ((int) $firstOfMonth->format('N')) - 1;
        $trailingDays = 7 - ((int) $lastOfMonth->format('N'));
        $gridStart = $firstOfMonth->modify(sprintf('-%d days', $leadingDays));
        $gridEnd = $lastOfMonth->modify(sprintf('+%d days', $trailingDays));

        $reminderCounts = $this->reminderRepository->countByDateInRange($gridStart, $gridEnd);

        $weeks = [];
        $week = [];
        $cursor = $gridStart;
        while ($cursor <= $gridEnd) {
            $key = $cursor->format('Y-m-d');
            $week[] = [
                'date' => $cursor,
                'day' => (int) $cursor->format('j'),
                'muted' => ((int) $cursor->format('n')) !== $month,
                'has_reminders' => isset($reminderCounts[$key]),
                'count' => $reminderCounts[$key] ?? 0,
            ];
            if (7 === \count($week)) {
                $weeks[] = $week;
                $week = [];
            }
            $cursor = $cursor->modify('+1 day');
        }

        $selectedDateParam = $request->query->get('date');
        $selectedDate = null;
        $selectedReminders = [];
        if (null !== $selectedDateParam) {
            $selectedDate = \DateTimeImmutable::createFromFormat('Y-m-d', $selectedDateParam);
            if (false !== $selectedDate) {
                $selectedDate = $selectedDate->setTime(0, 0, 0);
                $selectedReminders = $this->reminderRepository->findAllOn($selectedDate);
            } else {
                $selectedDate = null;
            }
        }

        $previousMonth = $firstOfMonth->modify('-1 month');
        $nextMonth = $firstOfMonth->modify('+1 month');

        $today = $now->setTime(0, 0, 0);
        $totalUpcoming = $this->reminderRepository->countFromDate($today);
        $totalPages = max(1, (int) ceil($totalUpcoming / self::UPCOMING_PAGE_SIZE));
        $upcomingPage = max(1, min($totalPages, (int) $request->query->get('page', 1)));
        $upcomingReminders = $this->reminderRepository->findPageFromDate($today, $upcomingPage, self::UPCOMING_PAGE_SIZE);

        $totalHistory = $this->reminderRepository->countBeforeDate($today);
        $historyTotalPages = max(1, (int) ceil($totalHistory / self::HISTORY_PAGE_SIZE));
        $historyPage = max(1, min($historyTotalPages, (int) $request->query->get('history_page', 1)));
        $historyReminders = $this->reminderRepository->findPageBeforeDate($today, $historyPage, self::HISTORY_PAGE_SIZE);

        $monthTotal = array_sum($this->reminderRepository->countByDateInRange($firstOfMonth, $lastOfMonth));
        $nextReminder = $this->reminderRepository->findPageFromDate($today, 1, 1)[0] ?? null;
        $daysUntilNext = null !== $nextReminder ? (int) $today->diff($nextReminder->getDate())->days : null;

        return new Response($this->twig->render('recordatorios/index.html.twig', [
            'month_date' => $firstOfMonth,
            'weeks' => $weeks,
            'previous_month' => $previousMonth,
            'next_month' => $nextMonth,
            'selected_date' => $selectedDate,
            'selected_reminders' => $selectedReminders,
            'new_reminder_date' => ($selectedDate ?? $today)->format('Y-m-d'),
            'upcoming_reminders' => $upcomingReminders,
            'upcoming_page' => $upcomingPage,
            'upcoming_total_pages' => $totalPages,
            'upcoming_total' => $totalUpcoming,
            'history_reminders' => $historyReminders,
            'history_page' => $historyPage,
            'history_total_pages' => $historyTotalPages,
            'history_total' => $totalHistory,
            'total_reminders' => $totalUpcoming + $totalHistory,
            'month_total' => $monthTotal,
            'next_reminder' => $nextReminder,
            'days_until_next' => $daysUntilNext,
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

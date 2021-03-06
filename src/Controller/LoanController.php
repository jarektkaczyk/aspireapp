<?php

namespace App\Controller;

use App\Entity\Loan;
use App\Factory\LoanFactory;
use App\Form\AdminLoanType;
use App\Form\LoanType;
use App\Repository\LoanRepaymentRepository;
use App\Repository\LoanRepository;
use App\Service\Repayment\RepaymentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;


class LoanController extends AbstractController
{
    /**
     * @Route("/admin/loan/", name="loan_index", methods={"GET"})
     */
    public function index(LoanRepository $loanRepository): Response
    {
        return $this->render('loan/index.html.twig', [
            'loans' => $loanRepository->findAll(),
        ]);
    }

    /**
     * Action to display loans of current user
     *
     * @Route("/", name="my_loan", methods={"GET"})
     */
    public function myLoans(LoanRepository $loanRepository, Security $security): Response
    {
        if (in_array('ROLE_ADMIN', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('loan_index');
        }

        $currentUser = $this->getUser();
        return $this->render('loan/my_loans.html.twig', [
            'loans' => $loanRepository->findBy(['user' => $currentUser]),
        ]);
    }

    /**
     * @Route("/loan/new", name="loan_new", methods={"GET","POST"})
     */
    public function new(Request $request, LoanFactory $loanFactory): Response
    {
        $loan = $loanFactory->createDefaultLoan();
        $form = $this->createForm(LoanType::class, $loan);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($loan);
            $entityManager->flush();

            return $this->redirectToRoute('my_loan');
        }

        return $this->render('loan/new.html.twig', [
            'loan' => $loan,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/loan/{id}", name="loan_show", methods={"GET"})
     */
    public function show(Loan $loan, LoanRepaymentRepository $loanRepaymentRepository, RepaymentService $repaymentService): Response
    {
        $loanRepayments = $loanRepaymentRepository->findBy(['loan' => $loan->getId()], ['id' => 'desc']);
        $repaidSummary = $repaymentService->getLoanRepaidSummary($loan);

        return $this->render('loan/show.html.twig', [
            'loan' => $loan,
            'loan_repayments' => $loanRepayments,
            'repaid_summary' => $repaidSummary
        ]);
    }

    /**
     * @Route("/admin/loan/{id}/edit", name="loan_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Loan $loan, LoanFactory $loanFactory): Response
    {
        $form = $this->createForm(AdminLoanType::class, $loan);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $loanFactory->handleApproveLoan($loan);
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('loan_index');
        }

        return $this->render('loan/edit.html.twig', [
            'loan' => $loan,
            'form' => $form->createView(),
        ]);
    }
}

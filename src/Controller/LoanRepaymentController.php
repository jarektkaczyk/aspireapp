<?php

namespace App\Controller;

use App\Entity\LoanRepayment;
use App\Factory\LoanFactory;
use App\Factory\LoanRepaymentFactory;
use App\Form\LoanRepaymentType;
use App\Repository\LoanRepaymentRepository;
use App\Repository\LoanRepository;
use http\Exception\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;


class LoanRepaymentController extends AbstractController
{
    /**
     * @Route("/admin/loan/repayment", name="loan_repayment_index", methods={"GET"})
     */
    public function index(LoanRepaymentRepository $loanRepaymentRepository): Response
    {
        return $this->render('loan_repayment/index.html.twig', [
            'loan_repayments' => $loanRepaymentRepository->findBy([], ['id' => 'desc']),
        ]);
    }

    /**
     * @Route("/loan/repayment/new", name="loan_repayment_new", methods={"GET","POST"})
     */
    public function new(
        Request $request, LoanRepository $loanRepository,
        LoanRepaymentFactory $repaymentFactory,
        LoanFactory $loanFactory
    ): Response
    {
        if($request->isMethod('POST') === false) {
            throw new NotFoundHttpException("Invalid request");
        }

        $loanId = (int)$request->request->get('loanId');
        $loan = $loanRepository->find($loanId);
        if (!$loan) {
            throw new NotFoundHttpException("Invalid Loan");
        }

        if ($loan->isApproved() === false) {
            throw new InvalidArgumentException("Can not Repay for unapproved loan");
        }

        $repayment = $repaymentFactory->createFromLoan($loan);
        //Change next repayment date after paying
        $loanFactory->changeNextRepaymentDate($loan);

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($repayment);
        $entityManager->flush();

        return $this->redirectToRoute('loan_show', ['id' => $loanId]);
    }



    /**
     * @Route("/admin/loan/repayment/{id}", name="loan_repayment_approve", methods={"POST"})
     */
    public function approve(Request $request, LoanRepayment $loanRepayment): Response
    {
        if ($this->isCsrfTokenValid('approve'.$loanRepayment->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $loanRepayment->setStatus(LoanRepayment::STATUS_SUCCESS);
            $entityManager->flush();
        }

        return $this->redirectToRoute('loan_repayment_index');
    }
}

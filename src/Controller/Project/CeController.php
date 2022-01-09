<?php

/*
 * This file is part of the Incipio package.
 *
 * (c) Florian Lefevre
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller\Project;

use App\Entity\Project\Ce;
use App\Entity\Project\Etude;
use App\Form\Project\CeType;
use App\Service\Project\DocTypeManager;
use App\Service\Project\EtudePermissionChecker;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class CeController extends AbstractController
{
    /**
     * @Security("has_role('ROLE_SUIVEUR')")
     * @Route(name="project_ce_rediger", path="/suivi/ce/rediger/{type}/{id}", methods={"GET","HEAD","POST"})
     *
     * @param Etude $etude etude which CE should belong to
     * @param int   $type  integer representing the type of document to edit. 0 for CE, 1 for BDC. Using such trick because CE & BDC are different documents but implemented by same entity because of similarities.
     *
     * @return RedirectResponse|Response
     */
    public function rediger(Request $request, Etude $etude, EtudePermissionChecker $permChecker, DocTypeManager $docTypeManager, int $type)
    {
        $em = $this->getDoctrine()->getManager();

        if ($permChecker->confidentielRefus($etude, $this->getUser())) {
            throw new AccessDeniedException('Cette étude est confidentielle');
        }

        if (!$ce = $etude->getCe()) {
            $ce = new Ce();
            $ce->setType($type);
            if ($etude->getCcaActive()) {
                $ce->setCca($etude->getCca());
            }

            $etude->setCe($ce);
        }

        $form = $this->createForm(CeType::class, $etude, ['prospect' => $etude->getProspect()]);

        if ('POST' == $request->getMethod()) {
            $form->handleRequest($request);

            if ($form->isValid()) {
                $docTypeManager->checkSaveNewEmploye($etude->getCe());

                if ($etude->getCcaActive() && $etude->getCca()) {
                    $etude->getCca()->addBdc($ce);
                }

                $em->flush();

                $message = Ce::TYPE_BDC === $type ? 'BDC modifié' : 'CE modifiée';
                $this->addFlash('success', $message);

                if ($request->get('phases')) {
                    return $this->redirectToRoute('project_phases_modifier', ['id' => $etude->getId()]);
                }

                return $this->redirectToRoute('project_etude_voir', ['nom' => $etude->getNom(), '_fragment' => 'tab3']);
            }
        }

        return $this->render('Project/Ce/rediger.html.twig', [
            'form' => $form->createView(),
            'ce' => $ce,
            'etude' => $etude,
        ]);
    }
}

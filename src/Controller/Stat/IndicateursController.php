<?php

/*
 * This file is part of the Incipio package.
 *
 * (c) Florian Lefevre
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller\Stat;

use App\Entity\Formation\Formation;
use App\Entity\Hr\Competence;
use App\Entity\Personne\Mandat;
use App\Entity\Personne\Membre;
use App\Entity\Project\Cc;
use App\Entity\Project\Ce;
use App\Entity\Project\Etude;
use App\Entity\Project\Mission;
use App\Entity\Treso\BV;
use App\Entity\Treso\NoteDeFrais;
use App\Service\KeyValueStore\Api\KeyValueStore;
use App\Service\Project\EtudeManager;
use App\Service\Stat\ChartFactory;
use Doctrine\Common\Persistence\ObjectManager;
use Ob\HighchartsBundle\Highcharts\Highchart;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class IndicateursController extends AbstractController
{
    const STATE_ID_EN_COURS_X = 2;

    const STATE_ID_TERMINEE_X = 4;

    private $chartFactory;

    private $etudeManager;

    private $keyValueStore;

    const INDICATEURS = [
        'suivi' => ['stat_ajax_suivi_retardParMandat',
                    'stat_ajax_suivi_nombreEtudes',
                    'stat_ajax_suivi_tauxAvenantsParMandat',
        ],
        'treso' => ['stat_ajax_treso_repartitionSorties',
                    'stat_ajax_treso_sorties',
                    'stat_ajax_treso_caParMandatHistogram',
                    'stat_ajax_treso_caParMandatCourbe',
        ],
        'asso' => ['stat_ajax_asso_nombreMembres',
                   'stat_ajax_asso_membresParPromo',
                   'stat_ajax_asso_intervenantsParPromo',
        ],
        'formations' => ['stat_ajax_formations_nombreDePresentFormationsTimed',
                         'stat_ajax_formations_nombreFormationsParMandat',
        ],
        'devco' => ['stat_ajax_devco_repartitionClientSelonChiffreAffaire',
                    'stat_ajax_devco_repartitionClientParNombreDEtude',
                    'stat_ajax_devco_caParMandatCourbe',
                    'stat_ajax_devco_sourceProspectionParNombreDEtude',
                    'stat_ajax_devco_sourceProspectionSelonChiffreAffaire',
                    'stat_ajax_devco_caCompetences',
        ],
    ];

    public function __construct(ChartFactory $chartFactory, EtudeManager $etudeManager, KeyValueStore $keyValueStore)
    {
        $this->chartFactory = $chartFactory;
        $this->etudeManager = $etudeManager;
        $this->keyValueStore = $keyValueStore;
    }

    /**
     * @Security("has_role('ROLE_CA')")
     * @Route(name="stat_index", path="/admin/indicateurs", methods={"GET","HEAD"})
     */
    public function index()
    {
        $statsBrutes = ['Pas de données' => 'A venir'];

        return $this->render(
            'Stat/Indicateurs/index.html.twig',
            ['stats' => $statsBrutes, 'indicateurs' => self::INDICATEURS]
        );
    }

    /**
     * @Security("has_role('ROLE_CA')")
     * @Route(name="stat_ajax_suivi_retardParMandat", path="/admin/indicateurs/etudes/retardParMandat", methods={"GET","HEAD"})
     *
     * Retard par mandat (gestion d'études)
     * Basé sur les dates de signature CC/CE et non pas les numéros
     *
     * @return Response
     */
    public function getRetardParMandat(Request $request, ObjectManager $em)
    {
        $ccs = $em->getRepository(Cc::class)->findBy([], ['dateSignature' => 'asc']);
        $ces = $em->getRepository(Ce::class)->findBy([], ['dateSignature' => 'asc']);

        $nombreJoursParMandat = [];
        $nombreJoursAvecAvenantParMandat = [];

        foreach ($ccs as $cc) {
            $etude = $cc->getEtude();
            $dateSignature = $cc->getDateSignature();
            $signee = self::STATE_ID_EN_COURS_X == $etude->getStateID() || self::STATE_ID_TERMINEE_X == $etude->getStateID();
            $mandat = $etude->getMandat();

            if ($dateSignature && $signee && $etude->getDelai()) {
                if (array_key_exists($mandat, $nombreJoursParMandat)) {
                    $nombreJoursParMandat[$mandat] += $etude->getDelai(false)->days;
                    $nombreJoursAvecAvenantParMandat[$mandat] += $etude->getDelai(true)->days;
                } else {
                    $nombreJoursParMandat[$mandat] = $etude->getDelai(false)->days;
                    $nombreJoursAvecAvenantParMandat[$mandat] = $etude->getDelai(true)->days;
                }
            }
        }

        foreach ($ces as $ce) {
            $etude = $ce->getEtude();
            $dateSignature = $ce->getDateSignature();
            $signee = self::STATE_ID_EN_COURS_X == $etude->getStateID() || self::STATE_ID_TERMINEE_X == $etude->getStateID();
            $mandat = $etude->getMandat();

            if ($dateSignature && $signee && $etude->getDelai()) {
                if (array_key_exists($mandat, $nombreJoursParMandat)) {
                    $nombreJoursParMandat[$mandat] += $etude->getDelai(false)->days;
                    $nombreJoursAvecAvenantParMandat[$mandat] += $etude->getDelai(true)->days;
                } else {
                    $nombreJoursParMandat[$mandat] = $etude->getDelai(false)->days;
                    $nombreJoursAvecAvenantParMandat[$mandat] = $etude->getDelai(true)->days;
                }
            }
        }

        $data = [];
        $categories = [];
        foreach ($nombreJoursParMandat as $mandat => $datas) {
            if ($datas > 0) {
                $categories[] = $mandat;
                $data[] = ['y' => 100 * ($nombreJoursAvecAvenantParMandat[$mandat] - $datas) / $datas,
                           'nombreEtudes' => $datas,
                           'nombreEtudesAvecAv' => $nombreJoursAvecAvenantParMandat[$mandat] - $datas,
                ];
            }
        }

        $series = [['name' => 'Nombre de jours de retard / nombre de jours travaillés', 'colorByPoint' => true,
                    'data' => $data,
                   ],
        ];
        $ob = $this->chartFactory->newColumnChart($series, $categories);

        $ob->chart->renderTo($request->get('_route'));
        $ob->title->text('Retard par Mandat');
        $ob->tooltip->headerFormat('<b>{series.name}</b><br/>');
        $ob->tooltip->pointFormat('Les études ont duré en moyenne {point.y:.2f} % de plus que prévu<br/>avec {point.nombreEtudesAvecAv} jours de retard sur {point.nombreEtudes} jours travaillés');
        $ob->xAxis->title(['text' => 'Mandat']);
        $ob->yAxis->max(100);
        $ob->yAxis->title(['text' => 'Taux (%)']);

        return $this->render('Stat/Indicateurs/Indicateur.html.twig', [
            'chart' => $ob,
        ]);
    }

    /**
     * @Security("has_role('ROLE_CA')")
     * @Route(name="stat_ajax_suivi_nombreEtudes", path="/admin/indicateurs/etudes/nombresEtudes", methods={"GET","HEAD"})
     *
     * Nombre d'études par mandat (gestion d'études)
     *
     * @return Response
     */
    public function getNombreEtudes(Request $request, ObjectManager $em)
    {
        $ccs = $em->getRepository(Cc::class)->findBy([], ['dateSignature' => 'asc']);
        $ces = $em->getRepository(Ce::class)->findBy([], ['dateSignature' => 'asc']);

        $nombreEtudesParMandat = [];

        foreach ($ccs as $cc) {
            $etude = $cc->getEtude();
            $dateSignature = $cc->getDateSignature();
            $signee = self::STATE_ID_EN_COURS_X == $etude->getStateID() || self::STATE_ID_TERMINEE_X == $etude->getStateID();
            $mandat = $etude->getMandat();

            if ($dateSignature && $signee) {
                if (array_key_exists($mandat, $nombreEtudesParMandat)) {
                    ++$nombreEtudesParMandat[$mandat];
                } else {
                    $nombreEtudesParMandat[$mandat] = 1;
                }
            }
        }

        foreach ($ces as $ce) {
            $etude = $ce->getEtude();
            $dateSignature = $ce->getDateSignature();
            $signee = self::STATE_ID_EN_COURS_X == $etude->getStateID() || self::STATE_ID_TERMINEE_X == $etude->getStateID();
            $mandat = $etude->getMandat();

            if ($dateSignature && $signee) {
                if (array_key_exists($mandat, $nombreEtudesParMandat)) {
                    ++$nombreEtudesParMandat[$mandat];
                } else {
                    $nombreEtudesParMandat[$mandat] = 1;
                }
            }
        }

        $data = [];
        $categories = [];
        foreach ($nombreEtudesParMandat as $mandat => $datas) {
            if ($datas > 0) {
                $categories[] = $mandat;
                $data[] = ['y' => $datas];
            }
        }

        $series = [['name' => "Nombre d'études du mandat", 'colorByPoint' => true, 'data' => $data]];
        $ob = $this->chartFactory->newColumnChart($series, $categories);

        $ob->chart->renderTo($request->get('_route'));
        $ob->title->text('Nombre d\'études par mandat');
        $ob->tooltip->headerFormat('<b>{series.name}</b><br />');
        $ob->tooltip->pointFormat('{point.y} études');
        $ob->xAxis->title(['text' => 'Mandat']);
        $ob->yAxis->allowDecimals(false);
        $ob->yAxis->max(null);
        $ob->yAxis->title(['text' => 'Nombre d\'études']);

        return $this->render('Stat/Indicateurs/Indicateur.html.twig', [
            'chart' => $ob,
        ]);
    }

    /**
     * @Security("has_role('ROLE_CA')")
     * @Route(name="stat_ajax_suivi_tauxAvenantsParMandat", path="/admin/indicateurs/etudes/tauxAvenantsParMandat", methods={"GET","HEAD"})
     *
     * Taux d'avenants par mandat (gestion d'études)
     * Basé sur les dates de signature CC et non pas les numéros
     *
     * @return Response
     */
    public function getTauxAvenantsParMandat(Request $request, ObjectManager $em)
    {
        $ccs = $em->getRepository(Cc::class)->findBy([], ['dateSignature' => 'asc']);
        $ces = $em->getRepository(Ce::class)->findBy([], ['dateSignature' => 'asc']);

        $nombreEtudesParMandat = [];
        $nombreEtudesAvecAvenantParMandat = [];
        $nombreAvsParMandat = [];

        foreach ($ccs as $cc) {
            $etude = $cc->getEtude();
            $dateSignature = $cc->getDateSignature();
            $signee = self::STATE_ID_EN_COURS_X == $etude->getStateID() || self::STATE_ID_TERMINEE_X == $etude->getStateID();
            $mandat = $etude->getMandat();

            if ($dateSignature && $signee) {
                if (array_key_exists($mandat, $nombreEtudesParMandat)) {
                    ++$nombreEtudesParMandat[$mandat];
                } else {
                    $nombreEtudesParMandat[$mandat] = 1;
                    $nombreEtudesAvecAvenantParMandat[$mandat] = 0;
                    $nombreAvsParMandat[$mandat] = 0;
                }

                if (count($etude->getAvs()->toArray())) {
                    ++$nombreEtudesAvecAvenantParMandat[$mandat];
                    $nombreAvsParMandat[$mandat] += count($etude->getAvs()->toArray());
                }
            }
        }

        foreach ($ces as $ce) {
            $etude = $ce->getEtude();
            $dateSignature = $ce->getDateSignature();
            $signee = self::STATE_ID_EN_COURS_X == $etude->getStateID() || self::STATE_ID_TERMINEE_X == $etude->getStateID();
            $mandat = $etude->getMandat();

            if ($dateSignature && $signee) {
                if (array_key_exists($mandat, $nombreEtudesParMandat)) {
                    ++$nombreEtudesParMandat[$mandat];
                } else {
                    $nombreEtudesParMandat[$mandat] = 1;
                    $nombreEtudesAvecAvenantParMandat[$mandat] = 0;
                    $nombreAvsParMandat[$mandat] = 0;
                }

                if (count($etude->getAvs()->toArray())) {
                    ++$nombreEtudesAvecAvenantParMandat[$mandat];
                    $nombreAvsParMandat[$mandat] += count($etude->getAvs()->toArray());
                }
            }
        }

        $data = [];
        $categories = [];
        foreach ($nombreEtudesParMandat as $mandat => $datas) {
            if ($datas > 0) {
                $categories[] = $mandat;
                $data[] = ['y' => 100 * $nombreEtudesAvecAvenantParMandat[$mandat] / $datas,
                           'nombreEtudes' => $datas,
                           'nombreEtudesAvecAv' => $nombreEtudesAvecAvenantParMandat[$mandat],
                           'nombreAvs' => $nombreAvsParMandat[$mandat],
                ];
            }
        }

        $series = [['name' => "Taux d'avenants par mandat", 'colorByPoint' => true, 'data' => $data]];
        $ob = $this->chartFactory->newColumnChart($series, $categories);

        $ob->chart->renderTo($request->get('_route'));
        $ob->title->text('Taux d\'avenants du mandat');
        $ob->tooltip->headerFormat('<b>{series.name}</b><br />');
        $ob->tooltip->pointFormat('{point.y:.2f} %<br/>Avec {point.nombreEtudesAvecAv} sur {point.nombreEtudes} études<br/>Pour un total de {point.nombreAvs} Avenants');
        $ob->yAxis->max(100);
        $ob->xAxis->title(['text' => 'Mandat']);
        $ob->yAxis->title(['text' => 'Taux (%)']);

        return $this->render('Stat/Indicateurs/Indicateur.html.twig', [
            'chart' => $ob,
        ]);
    }

    /**
     * @Security("has_role('ROLE_CA')")
     * @Route(name="stat_ajax_treso_repartitionSorties", path="/admin/indicateurs/treso/repartitionSorties", methods={"GET","HEAD"})
     *
     * Répartition des dépenses HT selon les comptes comptables pour le mandat en cours (trésorerie)
     *
     * @return Response
     */
    public function getRepartitionSorties(Request $request, ObjectManager $em)
    {
        $mandat = $this->etudeManager->getmaxMandatCe();

        $nfs = $em->getRepository(NoteDeFrais::class)->findBy(['mandat' => $mandat]);
        $bvs = $em->getRepository(BV::class)->findBy(['mandat' => $mandat]);

        /* Initialisation */
        $comptes = [];
        $comptes['Honoraires BV'] = 0;
        $comptes['URSSAF'] = 0;
        $montantTotal = 0;

        foreach ($nfs as $nf) {
            foreach ($nf->getDetails() as $detail) {
                $compte = $detail->getCompte();
                if (null !== $compte) {
                    $compte = $detail->getCompte()->getLibelle();
                    $montantTotal += $detail->getMontantHT();
                    if (array_key_exists($compte, $comptes)) {
                        $comptes[$compte] += $detail->getMontantHT();
                    } else {
                        $comptes[$compte] = $detail->getMontantHT();
                    }
                }
            }
        }

        foreach ($bvs as $bv) {
            $comptes['Honoraires BV'] += $bv->getRemunerationBrute();
            $comptes['URSSAF'] += $bv->getPartJunior();
            $montantTotal += $bv->getRemunerationBrute() + $bv->getPartJunior();
        }

        ksort($comptes);
        $data = [];
        foreach ($comptes as $compte => $montantHT) {
            $data[] = ['name' => $compte, 'y' => (0 == $montantTotal) ? 0 : 100 * $montantHT / $montantTotal,
                       'montantHT' => $montantHT, 'montantTotal' => $montantTotal,
            ];
        }

        $series = [['type' => 'pie', 'name' => 'Répartition des dépenses HT', 'data' => $data,
                    'Dépenses totale' => $montantTotal,
                   ],
        ];
        $ob = $this->chartFactory->newPieChart($series);

        $ob->chart->renderTo($request->get('_route'));
        $ob->title->text('Répartition des dépenses HT selon les comptes comptables<br/>(Mandat en cours)');
        $ob->tooltip->headerFormat('<b>{series.name}</b><br />');
        $ob->tooltip->pointFormat('{point.name} : {point.montantHT:,.2f} € / {point.montantTotal:,.2f} € ({point.percentage:.1f}%)');

        return $this->render('Stat/Indicateurs/Indicateur.html.twig', [
            'chart' => $ob,
        ]);
    }

    /**
     * @Security("has_role('ROLE_CA')")
     * @Route(name="stat_ajax_treso_sorties", path="/admin/indicateurs/treso/sorties", methods={"GET","HEAD"})
     *
     * Montant HT des dépenses (trésorerie)
     *
     * @return Response
     */
    public function getSorties(Request $request, ObjectManager $em)
    {
        $sortiesParMandat = $em->getRepository(NoteDeFrais::class)->findAllByMandat();
        $bvsParMandat = $em->getRepository(BV::class)->findAllByMandat();

        $mandats = [];
        ksort($sortiesParMandat); // Tri selon les mandats
        /** @var NoteDeFrais[] $nfs */
        foreach ($sortiesParMandat as $mandat => $nfs) { // Pour chaque Mandat
            $mandats[$mandat] = ['Honoraires BV' => 0, 'URSSAF' => 0];
            foreach ($nfs as $nf) { // Pour chaque NF d'un mandat
                foreach ($nf->getDetails() as $detail) { // Pour chaque détail d'une NF
                    $compte = $detail->getCompte();
                    if (null !== $compte) {
                        $libelle = $compte->getLibelle();
                        if (array_key_exists($libelle, $mandats[$mandat])) {
                            $mandats[$mandat][$libelle] += $detail->getMontantHT();
                        } else {
                            $mandats[$mandat][$libelle] = $detail->getMontantHT();
                        }
                    }
                }
            }
        }

        foreach ($bvsParMandat as $mandat => $bvs) { // Pour chaque Mandat
            if (!array_key_exists($mandat, $mandats)) {
                $mandats[$mandat] = [];
            }

            /** @var BV[] $bvs */
            foreach ($bvs as $bv) {
                $mandats[$mandat]['Honoraires BV'] += $bv->getRemunerationBrute();
                $mandats[$mandat]['URSSAF'] += $bv->getPartJunior();
            }
        }

        ksort($mandats);

        $dataSeries = [];
        $drilldownSeries = [];
        foreach ($mandats as $mandat => $comptes) {
            $total = 0;
            $drilldownData = [];

            foreach ($comptes as $libelle => $compte) {
                $total += $compte;
                $drilldownData[] = [$libelle, round((float) $compte, 2)];
            }

            $drilldownSeries[] = ['name' => 'Dépenses du mandat ' . $mandat, 'id' => $mandat, 'data' => $drilldownData];
            $dataSeries[] = ['name' => 'Mandat ' . $mandat, 'y' => round((float) $total, 2), 'drilldown' => $mandat];
        }
        $series = [['name' => 'Montant des dépenses', 'colorByPoint' => true, 'data' => $dataSeries]];

        $ob = $this->chartFactory->newColumnDrilldownChart($series, $drilldownSeries);

        $ob->chart->renderTo($request->get('_route'));
        $ob->title->text('Montant HT des dépenses');
        $ob->subtitle->text('Sélectionnez une colonne pour en voir le détail');
        $ob->yAxis->title(['text' => 'Montant (€)']);
        $ob->yAxis->max(null);
        $ob->tooltip->headerFormat('<b>{series.name}</b><br />');
        $ob->tooltip->pointFormat('{point.y} € HT');

        return $this->render('Stat/Indicateurs/Indicateur.html.twig', [
            'chart' => $ob,
        ]);
    }

    /**
     * @Security("has_role('ROLE_CA')")
     * @Route(name="stat_ajax_treso_caParMandatHistogram", path="/admin/indicateurs/treso/caParMandatHistogram", methods={"GET","HEAD"})
     *
     * Chiffre d'affaires signé cumulé par mandat (trésorerie)
     *
     * @return Response
     */
    public function getCAParMandatHistogram(Request $request, ObjectManager $em)
    {
        $ccs = $em->getRepository(Cc::class)->findBy([], ['dateSignature' => 'asc']);
        $ces = $em->getRepository(Ce::class)->findBy([], ['dateSignature' => 'asc']);

        $cumuls = [];
        $cumulsJEH = [];
        $cumulsFraisDossier = [];

        foreach ($ccs as $cc) {
            $etude = $cc->getEtude();
            $dateSignature = $cc->getDateSignature();
            $signee = self::STATE_ID_EN_COURS_X == $etude->getStateID() || self::STATE_ID_TERMINEE_X == $etude->getStateID();
            $mandat = $etude->getMandat();

            if ($dateSignature && $signee) {
                if (array_key_exists($mandat, $cumuls)) {
                    $cumuls[$mandat] += $etude->getMontantHT();
                    $cumulsJEH[$mandat] += $etude->getNbrJEH();
                    $cumulsFraisDossier[$mandat] += $etude->getFraisDossier();
                } else {
                    $cumuls[$mandat] = $etude->getMontantHT();
                    $cumulsJEH[$mandat] = $etude->getNbrJEH();
                    $cumulsFraisDossier[$mandat] = $etude->getFraisDossier();
                }
            }
        }

        foreach ($ces as $ce) {
            $etude = $ce->getEtude();
            $dateSignature = $ce->getDateSignature();
            $signee = self::STATE_ID_EN_COURS_X == $etude->getStateID() || self::STATE_ID_TERMINEE_X == $etude->getStateID();
            $mandat = $etude->getMandat();

            if ($dateSignature && $signee) {
                if (array_key_exists($mandat, $cumuls)) {
                    $cumuls[$mandat] += $etude->getMontantHT();
                    $cumulsJEH[$mandat] += $etude->getNbrJEH();
                    $cumulsFraisDossier[$mandat] += $etude->getFraisDossier();
                } else {
                    $cumuls[$mandat] = $etude->getMontantHT();
                    $cumulsJEH[$mandat] = $etude->getNbrJEH();
                    $cumulsFraisDossier[$mandat] = $etude->getFraisDossier();
                }
            }
        }

        $data = [];
        $categories = [];
        foreach ($cumuls as $mandat => $datas) {
            if ($datas > 0) {
                $categories[] = $mandat;
                $data[] = ['y' => $datas, 'JEH' => $cumulsJEH[$mandat],
                           'moyJEH' => ($datas - $cumulsFraisDossier[$mandat]) / $cumulsJEH[$mandat],
                ];
            }
        }

        $series = [
            [
                'name' => 'CA signé',
                'colorByPoint' => true,
                'data' => $data,
                'dataLabels' => [
                    'enabled' => true,
                    'rotation' => -90,
                    'align' => 'right',
                    'format' => '{point.y}€',
                    'style' => [
                        'color' => '#FFFFFF',
                        'fontSize' => '18px',
                        'fontFamily' => 'Verdana, sans-serif',
                        'textShadow' => '0 0 2px black',
                    ],
                    'y' => 1,
                ],
            ],
        ];

        $ob = $this->chartFactory->newColumnChart($series, $categories);

        $ob->chart->renderTo($request->get('_route'));
        $ob->xAxis->title(['text' => 'Mandat']);
        $ob->yAxis->max(null);
        $ob->yAxis->title(['text' => 'CA (€)']);
        $ob->title->text('Chiffre d\'affaires signé cumulé par mandat');
        $ob->tooltip->headerFormat('<b>{series.name}</b><br />');
        $ob->tooltip->pointFormat('{point.y} €<br/>En {point.JEH} JEH<br/>Soit {point.moyJEH:.2f} €/JEH');

        return $this->render('Stat/Indicateurs/Indicateur.html.twig', [
            'chart' => $ob,
        ]);
    }

    /**
     * @Security("has_role('ROLE_CA')")
     * @Route(name="stat_ajax_treso_caParMandatCourbe", path="/admin/indicateurs/treso/caParMandatCourbe", methods={"GET","HEAD"})
     *
     * Evolution par mandat du chiffre d'affaires signé cumulé (trésorerie)
     *
     * @return Response
     */
    public function getCA(Request $request, ObjectManager $em)
    {
        $ccs = $em->getRepository(Cc::class)->findBy([], ['dateSignature' => 'asc']);
        $ces = $em->getRepository(Cc::class)->findBy([], ['dateSignature' => 'asc']);

        if ($this->keyValueStore->exists('namingConvention')) {
            $namingConvention = $this->keyValueStore->get('namingConvention');
        } else {
            $namingConvention = 'id';
        }

        $etudeManager = $this->etudeManager;
        $maxMandat = $etudeManager->getmaxMandatCe();

        $mandats = [];
        $cumuls = [];

        foreach ($ccs as $cc) {
            $etude = $cc->getEtude();
            $dateSignature = $cc->getDateSignature();
            $signee = self::STATE_ID_EN_COURS_X == $etude->getStateID() || self::STATE_ID_TERMINEE_X == $etude->getStateID();

            if ($dateSignature && $signee) {
                $idMandat = $etude->getMandat();
                $cumuls[$idMandat] += $etude->getMontantHT();
                $interval = new \DateInterval('P' . ($maxMandat - $idMandat) . 'Y');
                $dateDecale = clone $dateSignature;
                $dateDecale->add($interval);
                $mandats[$idMandat][]
                    = ['x' => $dateDecale->getTimestamp() * 1000,
                       'y' => $cumuls[$idMandat],
                       'name' => $etude->getReference($namingConvention) . ' - ' . $etude->getNom(),
                       'date' => $dateDecale->format('d/m/Y'),
                       'prix' => $etude->getMontantHT(),
                ];
            }
        }

        foreach ($ces as $cc) {
            $etude = $ce->getEtude();
            $dateSignature = $ce->getDateSignature();
            $signee = self::STATE_ID_EN_COURS_X == $etude->getStateID() || self::STATE_ID_TERMINEE_X == $etude->getStateID();

            if ($dateSignature && $signee) {
                $idMandat = $etude->getMandat();
                $cumuls[$idMandat] += $etude->getMontantHT();
                $interval = new \DateInterval('P' . ($maxMandat - $idMandat) . 'Y');
                $dateDecale = clone $dateSignature;
                $dateDecale->add($interval);
                $mandats[$idMandat][]
                    = ['x' => $dateDecale->getTimestamp() * 1000,
                       'y' => $cumuls[$idMandat],
                       'name' => $etude->getReference($namingConvention) . ' - ' . $etude->getNom(),
                       'date' => $dateDecale->format('d/m/Y'),
                       'prix' => $etude->getMontantHT(),
                ];
            }
        }

        $series = [];
        foreach ($mandats as $mandat => $data) {
            $series[] = ['name' => 'Mandat ' . $mandat, 'data' => $data];
        }

        $ob = $this->chartFactory->newLineChart($series);

        $ob->chart->renderTo($request->get('_route'));  // The #id of the div where to render the chart
        $ob->global->useUTC(false);
        $ob->title->text('Évolution par mandat du chiffre d\'affaires signé cumulé');
        $ob->tooltip->headerFormat('<b>{series.name}</b><br />');
        $ob->tooltip->pointFormat('{point.y} le {point.date}<br />{point.name} à {point.prix} €');
        $ob->xAxis->dateTimeLabelFormats(['month' => '%b']);
        $ob->xAxis->title(['text' => 'Date']);
        $ob->xAxis->type('datetime');
        $ob->yAxis->min(0);
        $ob->yAxis->title(['text' => "Chiffre d'affaire signé cumulé (€)"]);

        return $this->render('Stat/Indicateurs/Indicateur.html.twig', [
            'chart' => $ob,
        ]);
    }

    /**
     * @Security("has_role('ROLE_CA')")
     * @Route(name="stat_ajax_formations_nombreDePresentFormationsTimed", path="/admin/indicateurs/formations/nombreDePresentFormationsTimed", methods={"GET","HEAD"})
     *
     * Nombre de présents aux formations (formations)
     *
     * @return Response
     */
    public function getNombreDePresentFormationsTimed(Request $request, ObjectManager $em)
    {
        $formationsParMandat = $em->getRepository(Formation::class)->findAllByMandat();

        $maxMandat = [] !== $formationsParMandat ? max(array_keys($formationsParMandat)) : 0;
        $mandats = [];

        /** @var Formation[] $formations */
        foreach ($formationsParMandat as $mandat => $formations) {
            foreach ($formations as $formation) {
                if ($formation->getDateDebut()) {
                    $interval = new \DateInterval('P' . ($maxMandat - $mandat) . 'Y');
                    $dateDecale = clone $formation->getDateDebut();
                    $dateDecale->add($interval);
                    $mandats[$mandat][] = [
                        'x' => $dateDecale->getTimestamp() * 1000,
                        'y' => count($formation->getMembresPresents()), 'name' => $formation->getTitre(),
                        'date' => $dateDecale->format('d/m/Y'),
                    ];
                }
            }
        }

        $series = [];
        foreach ($mandats as $mandat => $data) {
            $series[] = ['name' => 'Mandat ' . $mandat, 'data' => $data];
        }

        $ob = $this->chartFactory->newLineChart($series);

        $ob->chart->renderTo($request->get('_route'));
        $ob->global->useUTC(false);

        $ob->title->text('Nombre de présents aux formations');
        $ob->tooltip->headerFormat('<b>{series.name}</b><br/>');
        $ob->tooltip->pointFormat('{point.y} présents le {point.date}<br/>{point.name}');
        $ob->xAxis->dateTimeLabelFormats(['month' => '%b']);
        $ob->xAxis->title(['text' => 'Date']);
        $ob->xAxis->type('datetime');
        $ob->yAxis->allowDecimals(false);
        $ob->yAxis->title(['text' => 'Nombre de présents']);
        $ob->yAxis->min(0);

        return $this->render('Stat/Indicateurs/Indicateur.html.twig', [
            'chart' => $ob,
        ]);
    }

    /**
     * @Security("has_role('ROLE_CA')")
     * @Route(name="stat_ajax_formations_nombreFormationsParMandat", path="/admin/indicateurs/formations/nombreFormationsParMandat", methods={"GET","HEAD"})
     *
     * Nombre de formations par mandat (formations)
     *
     * @return Response
     */
    public function getNombreFormationsParMandat(Request $request, ObjectManager $em)
    {
        $formationsParMandat = $em->getRepository(Formation::class)->findAllByMandat();

        $data = [];
        $categories = [];

        ksort($formationsParMandat); // Tri selon les promos
        foreach ($formationsParMandat as $mandat => $formations) {
            $data[] = count($formations);
            $categories[] = $mandat;
        }

        $series = [['name' => 'Nombre de formations', 'colorByPoint' => true, 'data' => $data]];
        $ob = $this->chartFactory->newColumnChart($series, $categories);

        $ob->chart->renderTo($request->get('_route'));
        $ob->title->text('Nombre de formations par mandat');
        $ob->tooltip->headerFormat('<b>{series.name}</b><br/>');
        $ob->tooltip->pointFormat('{point.y} formations');
        $ob->xAxis->title(['text' => 'Mandat']);
        $ob->yAxis->title(['text' => 'Nombre de formations']);
        $ob->yAxis->max(null);

        return $this->render('Stat/Indicateurs/Indicateur.html.twig', [
            'chart' => $ob,
        ]);
    }

    private function cmp($a, $b)
    {
        if ($a['date'] == $b['date']) {
            return 0;
        }

        return ($a['date'] < $b['date']) ? -1 : 1;
    }

    /**
     * @Security("has_role('ROLE_CA')")
     * @Route(name="stat_ajax_asso_nombreMembres", path="/admin/indicateurs/asso/nombreMembres", methods={"GET","HEAD"})
     *
     * Nombre de membres (gestion associative)
     *
     * @return Response
     */
    public function getNombreMembres(Request $request, ObjectManager $em)
    {
        /** @var Mandat[] $mandats */
        $mandats = $em->getRepository(Mandat::class)->getCotisantMandats();

        $promos = [];
        $cumuls = [];
        $dates = [];
        foreach ($mandats as $mandat) {
            if ($membre = $mandat->getMembre()) {
                $p = $membre->getPromotion();
                if (!in_array($p, $promos)) {
                    $promos[] = $p;
                }
                $dates[] = ['date' => $mandat->getDebutMandat(), 'type' => '1', 'promo' => $p];
                $dates[] = ['date' => $mandat->getFinMandat(), 'type' => '-1', 'promo' => $p];
            }
        }
        sort($promos);
        usort($dates, [$this, 'cmp']);

        foreach ($dates as $date) {
            $d = $date['date']->format('m/y');
            $p = $date['promo'];
            $t = $date['type'];
            foreach ($promos as $promo) {
                if (!array_key_exists($promo, $cumuls)) {
                    $cumuls[$promo] = [];
                }
                $cumuls[$promo][$d] = (array_key_exists(
                    $d,
                    $cumuls[$promo]
                ) ? $cumuls[$promo][$d] : (end($cumuls[$promo]) ? end($cumuls[$promo]) : 0));
            }
            $cumuls[$p][$d] += $t;
        }

        $series = [];
        $categories = (null !== $cumuls && count($promos) > 0 ? array_keys($cumuls[$promos[0]]) : []);
        foreach (array_reverse($promos) as $promo) {
            $series[] = ['name' => 'P' . $promo, 'data' => array_values($cumuls[$promo])];
        }

        $ob = $this->chartFactory->newLineChart($series);

        $ob->chart->renderTo($request->get('_route'));
        $ob->chart->type('area');
        $ob->chart->zoomType('x');
        $ob->legend->reversed(false);
        $ob->xAxis->categories($categories);
        $ob->xAxis->labels(['rotation' => -45]);
        $ob->xAxis->title(['text' => 'Date']);
        $ob->yAxis->allowDecimals(false);
        $ob->yAxis->min(0);
        $ob->yAxis->title(['text' => 'Nombre de membres']);
        $ob->plotOptions->area(['stacking' => 'normal']);
        $ob->title->text('Nombre de membres');
        $ob->subtitle->text('Zoomable en sélectionnant une zone horizontalement');
        $ob->tooltip->shared(true);
        $ob->tooltip->valueSuffix(' cotisants');

        return $this->render('Stat/Indicateurs/Indicateur.html.twig', [
            'chart' => $ob,
        ]);
    }

    /**
     * @Security("has_role('ROLE_CA')")
     * @Route(name="stat_ajax_asso_membresParPromo", path="/admin/indicateurs/asso/membresParPromo", methods={"GET","HEAD"})
     *
     * Nombres de membres par promotion (gestion associative)
     *
     * @return Response
     */
    public function getMembresParPromo(Request $request, ObjectManager $em)
    {
        $membres = $em->getRepository(Membre::class)->findAll();

        $promos = [];
        foreach ($membres as $membre) {
            $p = $membre->getPromotion();
            if ($p) {
                array_key_exists($p, $promos) ? $promos[$p]++ : $promos[$p] = 1;
            }
        }

        $data = [];
        $categories = [];
        ksort($promos); // Tri selon les promos
        foreach ($promos as $promo => $nombre) {
            $data[] = $nombre;
            $categories[] = 'P' . $promo;
        }

        $series = [['name' => 'Membres', 'colorByPoint' => true, 'data' => $data]];
        $ob = $this->chartFactory->newColumnChart($series, $categories);

        $ob->chart->renderTo($request->get('_route'));
        $ob->xAxis->title(['text' => 'Promotion']);
        $ob->yAxis->max(null);
        $ob->yAxis->title(['text' => 'Nombre de membres']);
        $ob->title->text('Nombre de membres par promotion');
        $ob->tooltip->headerFormat('<b>{series.name}</b><br/>');
        $ob->tooltip->pointFormat('{point.y}');

        return $this->render('Stat/Indicateurs/Indicateur.html.twig', [
            'chart' => $ob,
        ]);
    }

    /**
     * @Security("has_role('ROLE_CA')")
     * @Route(name="stat_ajax_asso_intervenantsParPromo", path="/admin/indicateurs/asso/intervenantsParPromo", methods={"GET","HEAD"})
     *
     * Nombre d'intervenants par promotion (gestion associative)
     *
     * @return Response
     */
    public function getIntervenantsParPromo(Request $request, ObjectManager $em)
    {
        $intervenants = $em->getRepository(Membre::class)->getIntervenantsParPromo();

        $promos = [];
        foreach ($intervenants as $intervenant) {
            $p = $intervenant->getPromotion();
            if ($p) {
                array_key_exists($p, $promos) ? $promos[$p]++ : $promos[$p] = 1;
            }
        }

        $data = [];
        $categories = [];
        foreach ($promos as $promo => $nombre) {
            $data[] = $nombre;
            $categories[] = 'P' . $promo;
        }

        $series = [['name' => 'Intervenants', 'colorByPoint' => true, 'data' => $data]];
        $ob = $this->chartFactory->newColumnChart($series, $categories);

        $ob->chart->renderTo($request->get('_route'));
        $ob->xAxis->title(['text' => 'Promotion']);
        $ob->yAxis->max(null);
        $ob->yAxis->title(['text' => "Nombre d'intervenants"]);
        $ob->title->text('Nombre d\'intervenants par promotion');
        $ob->tooltip->headerFormat('<b>{series.name}</b><br />');
        $ob->tooltip->pointFormat('{point.y}');

        return $this->render('Stat/Indicateurs/Indicateur.html.twig', [
            'chart' => $ob,
        ]);
    }

    /**
     * @Security("has_role('ROLE_CA')")
     *
     * Not used at the moment
     *
     * @return Response
     */
    public function getRh(Request $request, ObjectManager $em)
    {
        $etudeManager = $this->etudeManager;
        $missions = $em->getRepository(Mission::class)
            ->findBy([], ['debutOm' => 'asc']);
        if ($this->keyValueStore->exists('namingConvention')) {
            $namingConvention = $this->keyValueStore->get('namingConvention');
        } else {
            $namingConvention = 'id';
        }
        $mandats = [];
        $maxMandat = $etudeManager->getmaxMandatCe();

        $cumuls = [];
        for ($i = 0; $i <= $maxMandat; ++$i) {
            $cumuls[$i] = 0;
        }

        $mandats[1] = [];

        //Etape 1 remplir toutes les dates
        foreach ($missions as $mission) {
            $etude = $mission->getEtude();
            $dateDebut = $mission->getdebutOm();
            $dateFin = $mission->getfinOm();

            if ($dateDebut && $dateFin) {
                ++$cumuls[0];

                $dateDebutDecale = clone $dateDebut;
                $dateFinDecale = clone $dateFin;

                $addDebut = true;
                $addFin = true;
                foreach ($mandats[1] as $datePoint) {
                    if (($dateDebutDecale->getTimestamp() * 1000) == $datePoint['x']) {
                        $addDebut = false;
                    }
                    if (($dateFinDecale->getTimestamp() * 1000) == $datePoint['x']) {
                        $addFin = false;
                    }
                }

                if ($addDebut) {
                    $mandats[1][]
                        = ['x' => $dateDebutDecale->getTimestamp() * 1000,
                           'y' => 0/* $cumuls[0] */ ,
                           'name' => $etude->getReference($namingConvention) . ' + ' . $etude->getNom(),
                           'date' => $dateDebutDecale->format('d/m/Y'),
                           'prix' => $etude->getMontantHT(),
                    ];
                }
                if ($addFin) {
                    $mandats[1][]
                        = ['x' => $dateFinDecale->getTimestamp() * 1000,
                           'y' => 0/* $cumuls[0] */ ,
                           'name' => $etude->getReference($namingConvention) . ' - ' . $etude->getNom(),
                           'date' => $dateDebutDecale->format('d/m/Y'),
                           'prix' => $etude->getMontantHT(),
                    ];
                }
            }
        }

        //Etapes 2 trie dans l'ordre
        $callback = function ($a, $b) use ($mandats) {
            return $mandats[1][$a]['x'] > $mandats[1][$b]['x'];
        };
        uksort($mandats[1], $callback);
        foreach ($mandats[1] as $entree) {
            $mandats[2][] = $entree;
        }
        $mandats[1] = [];

        //Etapes 3 ++ --
        foreach ($missions as $mission) {
            $dateFin = $mission->getfinOm();
            $dateDebut = $mission->getdebutOm();

            if ($dateDebut && $dateFin) {
                $dateDebutDecale = clone $dateDebut;
                $dateFinDecale = clone $dateFin;

                foreach ($mandats[2] as &$entree) {
                    if ($entree['x'] >= $dateDebutDecale->getTimestamp() * 1000 && $entree['x'] < $dateFinDecale->getTimestamp() * 1000) {
                        ++$entree['y'];
                    }
                }
            }
        }

        // Chart
        $series = [];
        foreach ($mandats as $idMandat => $data) {
            $series[] = ['name' => 'Mandat ' . $idMandat, 'data' => $data];
        }

        $style = ['color' => '#000000', 'fontWeight' => 'bold', 'fontSize' => '16px'];

        $ob = new Highchart();
        $ob->global->useUTC(false);

        //WARN :::

        $ob->chart->renderTo($request->get('_route'));  // The #id of the div where to render the chart
        ///
        $ob->chart->type('spline');
        $ob->title->text("Évolution par mandat du nombre d'intervenant");
        $ob->xAxis->title(['text' => 'Date']);
        $ob->xAxis->type('datetime');
        $ob->xAxis->dateTimeLabelFormats(['month' => '%b']);
        $ob->yAxis->min(0);
        $ob->yAxis->title(['text' => "Nombre d'intervenant"]);
        $ob->tooltip->headerFormat('<b>{series.name}</b><br />');
        $ob->credits->enabled(false);
        $ob->legend->floating(true);
        $ob->legend->layout('vertical');
        $ob->legend->y(40);
        $ob->legend->x(90);
        $ob->legend->verticalAlign('top');
        $ob->legend->reversed(true);
        $ob->legend->align('left');
        $ob->legend->backgroundColor('#FFFFFF');
        $ob->legend->itemStyle($style);
        $ob->plotOptions->series(['lineWidth' => 5, 'marker' => ['radius' => 8]]);
        $ob->series($series);

        return $this->render('Stat/Indicateurs/Indicateur.html.twig', [
            'chart' => $ob,
        ]);
    }

    /**
     * @Security("has_role('ROLE_CA')")
     * @Route(name="stat_ajax_devco_repartitionClientSelonChiffreAffaire", path="/admin/indicateurs/devco/repartitionClientSelonChiffreAffaire", methods={"GET","HEAD"})
     *
     * Répartition du CA selon le type de client (développement commercial)
     *
     * @return Response
     */
    public function getRepartitionClientSelonChiffreAffaire(Request $request, ObjectManager $em)
    {
        $etudes = $em->getRepository(Etude::class)->findAll();

        $chiffreDAffairesTotal = 0;
        $repartitions = [];
        foreach ($etudes as $etude) {
            if (self::STATE_ID_EN_COURS_X == $etude->getStateID() || self::STATE_ID_TERMINEE_X == $etude->getStateID()) {
                $type = $etude->getProspect()->getEntiteToString();
                $CA = $etude->getMontantHT();
                $chiffreDAffairesTotal += $CA;
                array_key_exists($type, $repartitions) ? $repartitions[$type] += $CA : $repartitions[$type] = $CA;
            }
        }

        $data = [];
        foreach ($repartitions as $type => $CA) {
            if (null === $type) {
                $type = 'Autre';
            }
            $data[] = ['name' => $type, 'y' => round($CA / $chiffreDAffairesTotal * 100, 2), 'CA' => $CA];
        }

        $series = [['type' => 'pie', 'name' => 'Provenance du CA selon le type de client (tous mandats)',
                    'data' => $data, 'CA Total' => $chiffreDAffairesTotal,
                   ],
        ];
        $ob = $this->chartFactory->newPieChart($series);

        $ob->chart->renderTo($request->get('_route'));
        $ob->title->text("Répartition du CA selon le type de client ($chiffreDAffairesTotal € CA)");
        $ob->tooltip->headerFormat('<b>{point.key}</b><br />');
        $ob->tooltip->pointFormat('{point.percentage:.1f} %<br/>{point.CA} €');

        return $this->render('Stat/Indicateurs/Indicateur.html.twig', [
            'chart' => $ob,
        ]);
    }

    /**
     * @Security("has_role('ROLE_CA')")
     * @Route(name="stat_ajax_devco_repartitionClientParNombreDEtude", path="/admin/indicateurs/devco/repartitionClientParNombreDEtude", methods={"GET","HEAD"})
     *
     * Provenance des études selon le type de client (développement commercial)
     *
     * @return Response
     */
    public function getRepartitionClientParNombreDEtude(Request $request, ObjectManager $em)
    {
        $etudes = $em->getRepository(Etude::class)->findAll();

        $nombreClient = 0;
        $repartitions = [];

        /** @var Etude[] $etudes */
        foreach ($etudes as $etude) {
            if (self::STATE_ID_EN_COURS_X == $etude->getStateID() || self::STATE_ID_TERMINEE_X == $etude->getStateID()) {
                ++$nombreClient;
                $type = $etude->getProspect()->getEntiteToString();
                array_key_exists($type, $repartitions) ? $repartitions[$type]++ : $repartitions[$type] = 1;
            }
        }

        $data = [];
        foreach ($repartitions as $type => $nombre) {
            if (null === $type) {
                $type = 'Autre';
            }
            $data[] = ['name' => $type, 'y' => round($nombre / $nombreClient * 100, 2), 'nombre' => $nombre];
        }

        $series = [['type' => 'pie', 'name' => 'Provenance des études selon le type de client (tous mandats)',
                    'data' => $data, 'nombreClient' => $nombreClient,
                   ],
        ];
        $ob = $this->chartFactory->newPieChart($series);

        $ob->chart->renderTo($request->get('_route'));
        $ob->title->text('Provenance des études selon le type de client (' . $nombreClient . ' Etudes)');
        $ob->tooltip->headerFormat('<b>{point.key}</b><br />');
        $ob->tooltip->pointFormat('{point.percentage:.1f} %<br/>{point.nombre} études');

        return $this->render('Stat/Indicateurs/Indicateur.html.twig', [
            'chart' => $ob,
        ]);
    }

    /**
     * @Security("has_role('ROLE_CA')")
     * @Route(name="stat_ajax_devco_caParMandatCourbe", path="/admin/indicateurs/devco/partClientFideles", methods={"GET","HEAD"})
     *
     * Taux de fidélisation (développement commercial)
     *
     * @return Response
     */
    public function getPartClientFideles(Request $request, ObjectManager $em)
    {
        $etudes = $em->getRepository(Etude::class)->findAll();

        $clients = [];
        foreach ($etudes as $etude) {
            if (self::STATE_ID_EN_COURS_X == $etude->getStateID() || self::STATE_ID_TERMINEE_X == $etude->getStateID()) {
                $clientID = $etude->getProspect()->getId();
                if (array_key_exists($clientID, $clients)) {
                    ++$clients[$clientID];
                } else {
                    $clients[$clientID] = 1;
                }
            }
        }

        $repartitions = [];
        $nombreClient = count($clients);
        foreach ($clients as $clientID => $nombreEtude) {
            if (array_key_exists($nombreEtude, $repartitions)) {
                ++$repartitions[$nombreEtude];
            } else {
                $repartitions[$nombreEtude] = 1;
            }
        }

        /* Initialisation */
        $data = [];
        ksort($repartitions);
        foreach ($repartitions as $occ => $nbr) {
            $clientType = 1 == $occ ? "$nbr Nouveaux clients" : "$nbr Anciens clients avec $occ études";
            $data[] = ['name' => $clientType, 'y' => 100 * $nbr / $nombreClient];
        }

        $series = [['type' => 'pie', 'name' => 'Taux de fidélisation', 'data' => $data,
                    'Nombre de clients' => $nombreClient,
                   ],
        ];
        $ob = $this->chartFactory->newPieChart($series);

        $ob->chart->renderTo($request->get('_route'));
        $ob->title->text('Taux de fidélisation (% de clients ayant demandé plusieurs études)');
        $ob->tooltip->headerFormat('<b>{point.key}</b><br/>');
        $ob->tooltip->pointFormat('{point.percentage:.1f} %');

        return $this->render('Stat/Indicateurs/Indicateur.html.twig', [
            'chart' => $ob,
        ]);
    }

    /**
     * @Security("has_role('ROLE_CA')")
     * @Route(name="stat_ajax_devco_sourceProspectionParNombreDEtude", path="/admin/indicateurs/devco/sourceProspectionParNombreDEtude", methods={"GET","HEAD"})
     *
     * Provenance des études selon la source de prospection (développement commercial)
     *
     * @return Response
     */
    public function getSourceProspectionParNombreDEtude(Request $request, ObjectManager $em)
    {
        $etudes = $em->getRepository(Etude::class)->findAll();

        $nombreClient = 0;
        $repartitions = [];
        foreach ($etudes as $etude) {
            if (self::STATE_ID_EN_COURS_X == $etude->getStateID() || self::STATE_ID_TERMINEE_X == $etude->getStateID()) {
                ++$nombreClient;
                $type = $etude->getSourceDeProspectionToString();
                array_key_exists($type, $repartitions) ? $repartitions[$type]++ : $repartitions[$type] = 1;
            }
        }

        $data = [];
        foreach ($repartitions as $type => $nombre) {
            if (null === $type) {
                $type = 'Autre';
            }
            $data[] = ['name' => $type, 'y' => round($nombre / $nombreClient * 100, 2), 'nombre' => $nombre];
        }

        $series = [['type' => 'pie', 'name' => 'Provenance des études selon la source de prospection (tous mandats)',
                    'data' => $data, 'nombreClient' => $nombreClient,
                   ],
        ];
        $ob = $this->chartFactory->newPieChart($series);

        $ob->chart->renderTo($request->get('_route'));
        $ob->title->text('Provenance des études selon la source de prospection (' . $nombreClient . ' Etudes)');
        $ob->tooltip->headerFormat('<b>{point.key}</b><br />');
        $ob->tooltip->pointFormat('{point.percentage:.1f} %<br/>{point.nombre} études');

        return $this->render('Stat/Indicateurs/Indicateur.html.twig', [
            'chart' => $ob,
        ]);
    }

    /**
     * @Security("has_role('ROLE_CA')")
     * @Route(name="stat_ajax_devco_sourceProspectionSelonChiffreAffaire", path="/admin/indicateurs/devco/sourceProspectionSelonChiffreAffaire", methods={"GET","HEAD"})
     *
     * Répartition du CA selon la source de prospection (développement commercial)
     *
     * @return Response
     */
    public function getSourceProspectionSelonChiffreAffaire(Request $request, ObjectManager $em)
    {
        $etudes = $em->getRepository(Etude::class)->findAll();

        $chiffreDAffairesTotal = 0;
        $repartitions = [];
        foreach ($etudes as $etude) {
            if (self::STATE_ID_EN_COURS_X == $etude->getStateID() || self::STATE_ID_TERMINEE_X == $etude->getStateID()) {
                $type = $etude->getSourceDeProspectionToString();
                $CA = $etude->getMontantHT();
                $chiffreDAffairesTotal += $CA;
                array_key_exists($type, $repartitions) ? $repartitions[$type] += $CA : $repartitions[$type] = $CA;
            }
        }

        $data = [];
        foreach ($repartitions as $type => $CA) {
            if (null === $type) {
                $type = 'Autre';
            }
            $data[] = ['name' => $type, 'y' => round($CA / $chiffreDAffairesTotal * 100, 2), 'CA' => $CA];
        }

        $series = [['type' => 'pie', 'name' => 'Répartition du CA selon la source de prospection (tous mandats)',
                    'data' => $data, 'CA Total' => $chiffreDAffairesTotal,
                   ],
        ];
        $ob = $this->chartFactory->newPieChart($series);

        $ob->chart->renderTo($request->get('_route'));
        $ob->title->text("Répartition du CA selon la source de prospection ($chiffreDAffairesTotal € CA)");
        $ob->tooltip->headerFormat('<b>{point.key}</b><br />');
        $ob->tooltip->pointFormat('{point.percentage:.1f} %<br/>{point.CA} €');

        return $this->render('Stat/Indicateurs/Indicateur.html.twig', [
            'chart' => $ob,
        ]);
    }

    /**
     * @Security("has_role('ROLE_CA')")
     * @Route(name="stat_ajax_devco_caCompetences", path="/admin/indicateurs/devco/caCompetences", methods={"GET","HEAD"})
     *
     * A chart displaying how much a skill has brought in turnover
     *
     * @return Response
     */
    public function getCACompetences(Request $request, ObjectManager $em)
    {
        $etudeManager = $this->etudeManager;
        $MANDAT_MAX = $etudeManager->getMaxMandat();
        $MANDAT_MIN = $etudeManager->getMinMandat();

        /** @var Competence[] $competences */
        $competences = $em->getRepository(Competence::class)->getAllEtudesByCompetences();

        //how much each skill has make us earn.
        $series = [];
        $categories = [];
        $used_mandats = array_fill(
            0,
            $MANDAT_MAX - $MANDAT_MIN + 1,
            0
        ); // an array to post-process results and remove mandats without data.
        //create array structure
        foreach ($competences as $c) {
            $temp = [
                'name' => $c->getNom(),
                'data' => array_fill(0, $MANDAT_MAX - $MANDAT_MIN + 1, 0),
            ];

            $sumSkill = 0;
            foreach ($c->getEtudes() as $e) {
                $temp['data'][$e->getMandat() - $MANDAT_MIN] += $e->getMontantHT();
                ++$used_mandats[$e->getMandat() - $MANDAT_MIN];
                $sumSkill += $e->getMontantHT();
            }
            if ($sumSkill > 0) {
                $series[] = $temp;
            }
        }

        for ($i = $MANDAT_MIN; $i <= $MANDAT_MAX; ++$i) {
            $categories[] = $i;
        }

        //remove mandats with no skills used
        //once array has been spliced, index will be changed. Therefore, we uses $k has read index
        $k = 0;
        for ($i = 0; $i <= $MANDAT_MAX - $MANDAT_MIN; ++$i) {
            if (0 == $used_mandats[$i] && isset($categories[$k])) {
                array_splice($categories, $k, 1);
                $count_series = count($series);
                for ($j = 0; $j < $count_series; ++$j) {
                    array_splice($series[$j]['data'], $k, 1);
                }
            } else {
                ++$k;
            }
        }

        $ob = $this->chartFactory->newColumnChart($series, $categories);

        $ob->chart->renderTo($request->get('_route'));
        $ob->xAxis->title(['text' => 'Mandat']);
        $ob->yAxis->max(null);
        $ob->yAxis->title(['text' => 'Revenus (€)']);
        $ob->title->text('Revenus par compétences');
        $ob->tooltip->headerFormat('<b>Mandat {point.x}</b><br/>');

        $ob->legend->enabled(true);
        $ob->legend->backgroundColor('#F6F6F6');

        return $this->render('Stat/Indicateurs/Indicateur.html.twig', [
            'chart' => $ob,
        ]);
    }
}

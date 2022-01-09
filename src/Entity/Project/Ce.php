<?php

/*
 * This file is part of the Incipio package.
 *
 * (c) Florian Lefevre
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Entity\Project;

use App\Entity\Personne\Personne;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * BdC and Ce (named Ce for implementation reasons —almost same object, makes less duplicated code).
 *
 * @ORM\Table()
 * @ORM\Entity
 */
class Ce extends DocType
{
    const TYPE_CE = 0;

    const TYPE_BDC = 1;

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var Etude
     *
     * @ORM\OneToOne(targetEntity="Etude", mappedBy="ce")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     */
    protected $etude;

    /** nombre de developpeur estimé.
     * @var int
     *
     * @ORM\Column(name="nbrDev", type="integer", nullable=true)
     */
    private $nbrDev;

    /**
     * @var Personne
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Personne\Personne")
     * @ORM\JoinColumn(nullable=true)
     */
    protected $contact;

    /**
     * @var bool
     *
     * @ORM\Column(name="deonto", type="boolean", nullable=true)
     */
    private $deonto;

    /**
     * @Assert\NotNull()
     * @Assert\GreaterThanOrEqual(0)
     * @Assert\LessThanOrEqual(1)
     *
     * @ORM\Column(type="smallint")
     */
    private $type;

    /**
     * @ORM\ManyToOne(targetEntity=Cca::class, inversedBy="bdcs")
     */
    private $cca;

    public function getTypeToString()
    {
        return $this->getType() ? 'BDC' : 'CE';
    }

    public function getReference()
    {
        return $this->etude->getReference() . '/' . (null !== $this->getDateSignature() ? $this->getDateSignature()
            ->format('Y') : '') . '/' . $this->getTypeToString() . '/' . $this->getVersion();
    }

    /** auto-generated methods */

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set etude.
     *
     * @param Etude $etude
     *
     * @return Ce
     */
    public function setEtude(Etude $etude = null)
    {
        $this->etude = $etude;

        return $this;
    }

    /**
     * Get etude.
     *
     * @return Etude
     */
    public function getEtude()
    {
        return $this->etude;
    }

    public function __toString()
    {
        return $this->etude->getReference() . '/' . $this->getTypeToString() . '/';
    }

    /**
     * Set nbrDev.
     *
     * @param int $nbrDev
     *
     * @return Ce
     */
    public function setNbrDev($nbrDev)
    {
        $this->nbrDev = $nbrDev;

        return $this;
    }

    /**
     * Get nbrDev.
     *
     * @return int
     */
    public function getNbrDev()
    {
        return $this->nbrDev;
    }

    /**
     * Set contactMgate.
     *
     * @param Personne $contact
     *
     * @return Ce
     */
    public function setContact(Personne $contact = null)
    {
        $this->contact = $contact;

        return $this;
    }

    /**
     * Get contact.
     *
     * @return Personne
     */
    public function getContact()
    {
        return $this->contact;
    }

    /**
     * Set deonto.
     *
     * @param bool $deonto
     *
     * @return Ce
     */
    public function setDeonto($deonto)
    {
        $this->deonto = $deonto;

        return $this;
    }

    /**
     * Get deonto.
     *
     * @return bool
     */
    public function getDeonto()
    {
        return $this->deonto;
    }

    /**
     * Get type.
     *
     * @return int
     */
    public function getType(): ?int
    {
        return $this->type;
    }

    /**
     * Set type.
     *
     * @param int $type 0 => CE | 1 => BDC
     *
     * @return Facture
     */
    public function setType(int $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getCca(): ?Cca
    {
        return $this->cca;
    }

    public function setCca(?Cca $cca): self
    {
        $this->cca = $cca;

        return $this;
    }
}

<?php

namespace NandoJans\EntityTools\Trait;


use Doctrine\ORM\Mapping\Column;

trait FormFlowEntityTrait
{

    #[Column(type: 'string', nullable: true)]
    private ?string $currentFlowStep = null {
        get {
            return $this->currentFlowStep;
        }
        set {
            $this->currentFlowStep = $value;
        }
    }

}
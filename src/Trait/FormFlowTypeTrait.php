<?php

namespace NandoJans\EntityTools\Trait;

use Symfony\Component\OptionsResolver\OptionsResolver;

trait FormFlowTypeTrait
{

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'step_property_path' => 'currentFlowStep',
        ]);
    }
}

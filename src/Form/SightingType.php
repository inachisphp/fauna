<?php

/**
 * This file is part of the Fauna plugin for the Inachis framework
 *
 * @package Fauna
 * @license https://github.com/inachisphp/plugin-fauna/blob/main/LICENSE.md
 */

namespace Inachis\Fauna\Form;

use Inachis\Fauna\Entity\Country;
use Inachis\Fauna\Entity\Sighting;
use Inachis\Fauna\Entity\Species;
use Inachis\Fauna\Entity\Trip;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;

class SightingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('species', EntityType::class, [
                'class' => Species::class,
                'choice_label' => function (Species $species) {
                    return sprintf('%s (%s)', $species->getName(), $species->getLatin());
                },
                'placeholder' => 'Choose a species',
                'constraints' => [
                    new NotBlank(['message' => 'You must specify a species']),
                ],
            ])
            ->add('trip', EntityType::class, [
                'class' => Trip::class,
                'choice_label' => 'name',
                'placeholder' => 'No trip specified',
                'required' => false,
            ])
            ->add('country', EntityType::class, [
                'class' => Country::class,
                'choice_label' => 'name',
                'placeholder' => 'No country selected',
                'required' => false,
            ])
            ->add('location', TextType::class, [
                'required' => false,
                'attr' => ['placeholder' => 'Friendly place name (Optional)'],
            ])
            ->add('latitude', NumberType::class, [
                'required' => false,
                'scale' => 6,
            ])
            ->add('longitude', NumberType::class, [
                'required' => false,
                'scale' => 6,
            ])
            ->add('notes', TextareaType::class, [
                'required' => false,
                'attr' => ['rows' => 4],
            ])
            ->add('count', IntegerType::class, [
                'required' => true,
                'constraints' => [
                    new GreaterThanOrEqual([
                        'value' => 1,
                        'message' => 'Count must be at least 1',
                    ]),
                ],
            ])
            ->add('photograph', TextType::class, [
                'required' => false,
                'attr' => ['placeholder' => 'Image filename or URL'],
            ])
            ->add('date', DateTimeType::class, [
                'widget' => 'single_text',
                'required' => true,
                'input' => 'datetime_immutable',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Sighting::class,
        ]);
    }
}

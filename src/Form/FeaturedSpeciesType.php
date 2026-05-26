<?php

/**
 * This file is part of the Fauna plugin for the Inachis framework
 *
 * @package Fauna
 * @license https://github.com/inachisphp/plugin-fauna/blob/main/LICENSE.md
 */

namespace Inachis\Fauna\Form;

use Inachis\Fauna\Entity\FeaturedSpecies;
use Inachis\Fauna\Entity\Species;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class FeaturedSpeciesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'Featured title cannot be empty']),
                ],
                'attr' => ['placeholder' => 'Enter featured title'],
            ])
            ->add('species', EntityType::class, [
                'class' => Species::class,
                'choice_label' => function (Species $species) {
                    return sprintf('%s (%s)', $species->getName(), $species->getLatin());
                },
                'placeholder' => 'Select a species to feature',
                'constraints' => [
                    new NotBlank(['message' => 'You must select a species to feature']),
                ],
            ])
            ->add('imageLink', TextType::class, [
                'required' => false,
                'attr' => ['placeholder' => 'Link to source image'],
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'attr' => ['rows' => 6],
            ])
            ->add('scheduleDate', DateTimeType::class, [
                'widget' => 'single_text',
                'required' => false,
                'input' => 'datetime_immutable',
            ])
            ->add('isLive', CheckboxType::class, [
                'required' => false,
                'label' => 'Publish immediately',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FeaturedSpecies::class,
        ]);
    }
}

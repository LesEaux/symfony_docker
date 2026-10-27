<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Image;

final class CompanyLogoForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('logo', FileType::class, [
            'label' => 'Logo de l’entreprise',
            'required' => false,
            'attr' => ['accept' => 'image/png,image/jpeg,image/webp'],
            'help' => 'PNG, JPEG ou WebP. 2 Mo maximum et 2 048 × 2 048 pixels maximum. Le logo conserve ses proportions.',
            'constraints' => [new Image(
                maxSize: '2M',
                mimeTypes: ['image/png', 'image/jpeg', 'image/webp'],
                maxWidth: 2048,
                maxHeight: 2048,
                mimeTypesMessage: 'Choisissez une image PNG, JPEG ou WebP.',
            )],
        ])->add('removeLogo', CheckboxType::class, [
            'label' => 'Supprimer le logo actuel',
            'required' => false,
        ]);
    }
}

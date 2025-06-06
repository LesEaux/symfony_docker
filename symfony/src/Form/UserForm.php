<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email')
            ->add('roles', ChoiceType::class, [
                'label' => 'R�les',
                'choices' => [
                    'Utilisateur'       => 'utilisateur',
                    'Administrateur'    => 'admin',
                    // Ajoute ici d�autres r�les que tu utilises, si besoin
                ],
                'multiple' => true,
                'expanded' => true, // false si tu veux un <select> multiple, true pour checkboxes
            ])
            ->add('password')
            ->add('nom')
            ->add('prenom')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}

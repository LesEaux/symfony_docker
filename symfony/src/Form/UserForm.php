<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr'  => [
                    'class'     => 'form-control form-control-sm',
                ],
            ])
            ->add('roles', ChoiceType::class, [
                'label'    => 'R�le',
                'choices'  => [
                    'Utilisateur'    => 'Utilisateur',
                    'Administrateur' => 'Administrateur',
                ],
                'mapped'   => false,              // ne touche pas directement � $user->roles
                'multiple' => false,              // <select> simple
                'expanded' => false,
                'attr'     => [
                    'class' => 'form-select form-select-sm'
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label'    => 'Mot de passe',
                'mapped'   => false,
                'required' => true,
                'attr'     => ['class' => 'form-control form-control-sm'],
            ])
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

<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class UserForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $passwordConstraints = [new Assert\Length(min: 12, max: 4096)];
        if ($options['password_required']) {
            $passwordConstraints[] = new Assert\NotBlank();
        }
        $builder
            ->add('email', EmailType::class, ['label' => 'Email'])
            ->add('roles', ChoiceType::class, [
                'label' => 'Rôle',
                'choices' => ['Utilisateur' => 'ROLE_USER', 'Administrateur' => 'ROLE_ADMIN'],
                'mapped' => false,
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Mot de passe',
                'mapped' => false,
                'required' => $options['password_required'],
                'constraints' => $passwordConstraints,
                'help' => '12 caractères minimum. En modification, laisser vide pour conserver le mot de passe.',
                'attr' => ['autocomplete' => 'new-password'],
            ])
            ->add('nom')
            ->add('prenom', null, ['label' => 'Prénom'])
            ->add('isActive', CheckboxType::class, ['label' => 'Compte actif', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class, 'password_required' => false]);
        $resolver->setAllowedTypes('password_required', 'bool');
    }
}

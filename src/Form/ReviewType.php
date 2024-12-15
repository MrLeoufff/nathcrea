<?php

namespace App\Form;

use App\Entity\Review;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ReviewType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('content', TextareaType::class, [
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le contenu de l\'avis est obligatoire.']),
                    new Assert\Length([
                        'max' => 500,
                        'maxMessage' => 'Votre avis ne peut pas dépasser 500 caractères.',
                    ]),
                ],
            ])
            ->add('rating', IntegerType::class, [
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Veuillez fournir une note.']),
                    new Assert\Range([
                        'min' => 1,
                        'max' => 5,
                        'notInRangeMessage' => 'La note doit être entre {{ min }} et {{ max }}.',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Review::class,
        ]);
    }
}

<?php

namespace App\Form;

use App\Entity\Alternative;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class AlternativeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de l\'alternative',
                'attr' => ['placeholder' => 'Ex: Mecca Cola, Hamoud Boualem...'],
            ])
            ->add('brand', TextType::class, [
                'label' => 'Marque',
                'attr' => ['placeholder' => 'Ex: Alternative Brand Co.'],
            ])
            ->add('price', MoneyType::class, [
                'label'    => 'Prix (DT)',
                'currency' => false,
                'required' => false,
                'scale'    => 2,
                'attr'     => ['placeholder' => '0.00'],
            ])
            ->add('qualityRating', ChoiceType::class, [
                'label' => 'Qualité',
                'choices' => [
                    '★☆☆☆☆ - Basique' => 1,
                    '★★☆☆☆ - Correct' => 2,
                    '★★★☆☆ - Bon' => 3,
                    '★★★★☆ - Très bon' => 4,
                    '★★★★★ - Excellent' => 5,
                ],
            ])
            ->add('origin', TextType::class, [
                'label' => 'Pays d\'origine',
                'attr' => ['placeholder' => 'Ex: Tunisie, Palestine, Turquie...'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Pourquoi cette alternative est recommandée...',
                ],
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Image du produit',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                        'mimeTypesMessage' => 'Veuillez uploader une image valide (JPEG, PNG, WebP)',
                    ]),
                ],
            ])
        ;

        if ($options['show_for_sale']) {
            $builder->add('forSale', CheckboxType::class, [
                'label'    => 'Ajouter ce produit à la boutique (vendable)',
                'mapped'   => false,
                'required' => false,
                'data'     => $options['for_sale_default'],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'      => Alternative::class,
            'show_for_sale'   => false,
            'for_sale_default' => true,
        ]);
    }
}

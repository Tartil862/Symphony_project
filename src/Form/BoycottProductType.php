<?php

namespace App\Form;

use App\Entity\BoycottProduct;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class BoycottProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du produit',
                'attr' => ['placeholder' => 'Ex: Coca-Cola, Nestlé...'],
            ])
            ->add('brand', TextType::class, [
                'label' => 'Marque',
                'attr' => ['placeholder' => 'Ex: The Coca-Cola Company'],
            ])
            ->add('reason', ChoiceType::class, [
                'label' => 'Raison du boycott',
                'choices' => [
                    '🤝 Éthique' => BoycottProduct::REASON_ETHICAL,
                    '🏳️ Politique' => BoycottProduct::REASON_POLITICAL,
                    '🌿 Environnemental' => BoycottProduct::REASON_ENVIRONMENTAL,
                ],
                'placeholder' => 'Choisir une raison...',
            ])
            ->add('boycottLevel', ChoiceType::class, [
                'label' => 'Niveau du boycott',
                'choices' => [
                    '📍 Local' => BoycottProduct::LEVEL_LOCAL,
                    '🌍 Global' => BoycottProduct::LEVEL_GLOBAL,
                ],
                'placeholder' => 'Choisir le niveau...',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Pourquoi boycotter ce produit ?',
                'attr' => [
                    'rows' => 5,
                    'placeholder' => 'Décrivez les raisons détaillées du boycott...',
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
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BoycottProduct::class,
        ]);
    }
}

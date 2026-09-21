<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Topic;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TopicMergeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('origins', EntityType::class, [
                'class' => Topic::class,
                'choice_label' => 'name',
                'multiple' => true,
            ])
            ->add('destination', EntityType::class, [
                'class' => Topic::class,
                'choice_label' => 'name',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'topic_merge',
        ]);
    }
}

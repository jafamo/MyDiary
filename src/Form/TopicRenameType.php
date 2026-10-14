<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Topic;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TopicRenameType extends AbstractType
{
    /** Longitud de la columna `topic.name`. */
    public const NAME_MAX_LENGTH = 255;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, [
            'attr' => ['maxlength' => self::NAME_MAX_LENGTH],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Topic::class,
            'csrf_token_id' => 'topic_rename',
        ]);
    }
}

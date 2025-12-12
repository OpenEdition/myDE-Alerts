<?php

namespace MyDigitalEnvironment\AlertsBundle\Form;

use MyDigitalEnvironment\AlertsBundle\Entity\Search;
use MyDigitalEnvironment\AlertsBundle\Enum\Platform;
use MyDigitalEnvironment\AlertsBundle\Enum\SearchFormMode;
use MyDigitalEnvironment\AlertsBundle\Enum\UpdateFrequency;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ApiSearchType extends AbstractType
{
    public function __construct(private readonly UrlGeneratorInterface $router)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // todo : maybe replace by a simple 'hideFields' boolean ?
        $inPreview = $options['search_form_mode'] === SearchFormMode::RESULTS;
        $rowAttr = ['class' => $inPreview ? 'd-none' : 'mb-3'];
        $builder
            ->add('id', HiddenType::class)
            ->add('name', TextType::class, [
                'label' => 'alerts.form.name',
                'row_attr' => $rowAttr,
                'required' => false,
                'attr' => ['placeholder' => 'alerts.form.name.placeholder'],
            ])
            ->add('query', TextType::class, [
                'label' => 'alerts.form.all_fields',
                'row_attr' => $rowAttr,
                'attr' => ['placeholder' => 'alerts.form.all_fields.placeholder'],
                'required' => false,
            ])
            ->add('platforms', EnumType::class, [
                'class' => Platform::class,
                'label' => 'alerts.form.platform',
                'row_attr' => $rowAttr,
                'multiple' => true,
                'expanded' => true,
            ])
            ->add('fromPublication', IntegerType::class, [
                'label' => 'alerts.form.from_publication',
                'row_attr' => $rowAttr,
                'required' => false,
            ])
            ->add('untilPublication', IntegerType::class, [
                'label' => 'alerts.form.until_publication',
                'row_attr' => $rowAttr,
                'required' => false,
            ])
            ->add('frequency', EnumType::class, [
                'class' => UpdateFrequency::class,
                'row_attr' => $rowAttr,
                'label' => 'alerts.form.frequency',
                'expanded' => false,
            ])
            ->add('sendEmail', CheckboxType::class, [
                'label' => 'alerts.form.email',
                'row_attr' => $rowAttr,
                'required' => false,
            ])
            ->add('preview', SubmitType::class, ['label' => 'alerts.form.preview'])
            ->add('edit', SubmitType::class, ['label' => 'alerts.form.edit', 'attr' => ['formaction' => $this->router->generate('my_digital_environment_alerts_new_search')]])
            ->add('save', SubmitType::class, ['label' => 'alerts.form.save', 'attr' => ['formaction' => $this->router->generate('my_digital_environment_alerts_save_search')]])
            // ->add('advancedQuery')
            // ->add('platforms')
            // ->add('access')
            // ->add('languages')
            // ->add('authors')
            // ->add('journalsPublications')
            // ->add('booksPublications')
            // ->add('hypothesesPublications')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'my-de-alerts',
            'data_class' => Search::class,
            'search_form_mode' => SearchFormMode::EDITING,
        ]);

        $resolver->setAllowedTypes('search_form_mode', SearchFormMode::class);
    }
}

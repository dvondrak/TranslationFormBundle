<?php

namespace A2lix\TranslationFormBundle\Form\EventListener;

use A2lix\TranslationFormBundle\TranslationForm\TranslationForm;
use A2lix\TranslationFormBundle\Util\LegacyFormHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Validator\Constraints\FormValidator;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormConfigBuilder;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraint;
use Vanio\Stdlib\Objects;

/**
 * @author David ALLIX
 */
class TranslationsListener implements EventSubscriberInterface
{
    private $translationForm;

    /**
     *
     * @param \A2lix\TranslationFormBundle\TranslationForm\TranslationForm $translationForm
     */
    public function __construct(TranslationForm $translationForm)
    {
        $this->translationForm = $translationForm;
    }

    /**
     *
     * @param \Symfony\Component\Form\FormEvent $event
     */
    public function preSetData(FormEvent $event)
    {
        $form = $event->getForm();
        
        $translatableClass = $form->getParent()->getConfig()->getDataClass();
        $translationClass = $this->getTranslationClass($translatableClass);

        $formOptions = $form->getConfig()->getOptions();
        $fieldsOptions = $this->translationForm->getFieldsOptions($translationClass, $formOptions);

        if (isset($formOptions['locales'])) {
            foreach ($formOptions['locales'] as $locale) {
                if (isset($fieldsOptions[$locale])) {
                    $form->add(
                        $locale,
                        LegacyFormHelper::getType('A2lix\TranslationFormBundle\Form\Type\TranslationsFieldsType'),
                        array(
                            'data_class' => $translationClass,
                            'fields' => $fieldsOptions[$locale],
                            'required' => in_array($locale, $formOptions['required_locales'])
                        )
                    );
                }
            }
        }

        $validationGroups = $form->getConfig()->getOption('validation_groups');

        if ($form->getParent() && $validationGroups === null) {
            $formOptions['validation_groups'] = $this->resolveValidationGroups($form->getParent());
            Objects::setPropertyValue($form->getConfig(), 'options', $formOptions, FormConfigBuilder::class);
        }
    }
    
    /**
     *
     * @param \Symfony\Component\Form\FormEvent $event
     */
    public function submit(FormEvent $event)
    {
        $data = $event->getData();

        foreach ($data as $locale => $translation) {
            // Remove useless Translation object
            if (!$translation) {
                $data->removeElement($translation);
                
            } else {
                $translation->setLocale($locale);
            }
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
            FormEvents::PRE_SET_DATA => 'preSetData',
            FormEvents::SUBMIT => 'submit',
        );
    }
    
    /**
     *
     * @param string $translatableClass
     */
    private function getTranslationClass($translatableClass)
    {
        // Knp
        if (method_exists($translatableClass, "getTranslationEntityClass")) {
            return $translatableClass::getTranslationEntityClass();
        
        // Gedmo    
        } elseif (method_exists($translatableClass, "getTranslationClass")) {
            return $translatableClass::getTranslationClass();

        // Vanio Domain Bundle
        } elseif (method_exists($translatableClass, "translationClass")) {
            return $translatableClass::translationClass();
        }

        return $translatableClass .'Translation';
    }

    /**
     * @param FormInterface $form
     * @return string[]
     */
    private function resolveValidationGroups(FormInterface $form)
    {
        $resolveValidationGroups = function () use ($form) {
            return FormValidator::{'getValidationGroups'}($form);
        };
        $resolveValidationGroups = $resolveValidationGroups->bindTo(null, FormValidator::class);
        $validationGroups = $resolveValidationGroups();

        if ($validationGroups && !in_array(Constraint::DEFAULT_GROUP, $validationGroups)) {
            $validationGroups[] = Constraint::DEFAULT_GROUP;
        }

        return $validationGroups;
    }
}

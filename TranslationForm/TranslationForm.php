<?php

namespace A2lix\TranslationFormBundle\TranslationForm;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Symfony\Component\Form\FormRegistry,
    Symfony\Component\HttpKernel\Kernel,
    Doctrine\Common\Persistence\ManagerRegistry,
    Doctrine\Common\Util\ClassUtils,
    Doctrine\ORM\Mapping\ClassMetadata;

/**
 * @author David ALLIX
 */
class TranslationForm implements TranslationFormInterface
{
    private $typeGuesser;
    private $managerRegistry;

    /**
     *
     * @param \Symfony\Component\Form\FormRegistry $formRegistry
     * @param \Doctrine\Common\Persistence\ManagerRegistry $managerRegistry
     */
    public function __construct(FormRegistry $formRegistry, Registry $managerRegistry)
    {
        $this->typeGuesser = $formRegistry->getTypeGuesser();
        $this->managerRegistry = $managerRegistry;
    }

    /**
     *
     * @param string $translationClass
     * @return array
     */
    protected function getTranslationFields($translationClass)
    {
        $fields = array();
        $translationClass = ClassUtils::getRealClass($translationClass);

        if ($manager = $this->managerRegistry->getManagerForClass($translationClass)) {
            /** @var ClassMetadata $metadata */
            $metadata = $manager->getMetadataFactory()->getMetadataFor($translationClass);

            foreach ($metadata->fieldMappings as $fieldMapping) {
                $field = isset($fieldMapping['originalClass'])
                    ? $fieldMapping['declaredField']
                    : $fieldMapping['fieldName'];

                if (!isset($fields[$field]) && !in_array($field, array('id', 'locale'))) {
                    $fields[$field] = true;
                }
            }
        }

        return array_keys($fields);
    }

    /**
     * {@inheritdoc}
     */
    public function getFieldsOptions($class, $options)
    {
        $fieldsOptions = array();

        foreach ($this->getFieldsList($options, $class) as $field) {
            $fieldOptions = isset($options['fields'][$field]) ? $options['fields'][$field] : array();

            if (!isset($fieldOptions['display']) || $fieldOptions['display']) {
                $fieldOptions = $this->guessMissingFieldOptions($this->typeGuesser, $class, $field, $fieldOptions);

                // Custom options by locale
                if (isset($fieldOptions['locale_options'])) {
                    $localesFieldOptions = $fieldOptions['locale_options'];
                    unset($fieldOptions['locale_options']);

                    foreach ($options['locales'] as $locale) {
                        $localeFieldOptions = isset($localesFieldOptions[$locale]) ? $localesFieldOptions[$locale] : array();
                        if (!isset($localeFieldOptions['display']) || $localeFieldOptions['display']) {
                            $fieldsOptions[$locale][$field] = $localeFieldOptions + $fieldOptions;
                        }
                    }

                // General options for all locales
                } else {
                    foreach ($options['locales'] as $locale) {
                        $fieldsOptions[$locale][$field] = $fieldOptions;
                    }
                }
            }
        }

        return $fieldsOptions;
    }

    /**
     * Combine formFields with translationFields. (Useful for upload field)
     */
    private function getFieldsList($options, $class)
    {
        $formFields = $options['include_fields'] ?: array_keys($options['fields']);

        // Check existing
        foreach ($formFields as $field) {
            if (!property_exists($class, $field)) {
                throw new \Exception("Field '". $field ."' doesn't exist in ". $class);
            }
        }

        if (!$options['include_fields']) {
            $formFields = array_merge($formFields, $this->getTranslationFields($class));
        }

        return array_unique(array_diff($formFields, $options['exclude_fields']));
    }

    /**
     * {@inheritdoc}
     */
    public function getFormsOptions($options)
    {
        $formsOptions = array();

        // Current options
        $formOptions = $options['form_options'];

        // Custom options by locale
        if (isset($formOptions['locale_options'])) {
            $localesFormOptions = $formOptions['locale_options'];
            unset($formOptions['locale_options']);

            foreach ($options['locales'] as $locale) {
                $localeFormOptions = isset($localesFormOptions[$locale]) ? $localesFormOptions[$locale] : array();
                if (!isset($localeFormOptions['display']) || $localeFormOptions['display']) {
                    $formsOptions[$locale] = $localeFormOptions + $formOptions;
                }
            }

        // General options for all locales
        } else {
            foreach ($options['locales'] as $locale) {
                $formsOptions[$locale] = $formOptions;
            }
        }

        return $formsOptions;
    }

    /**
     * {@inheritdoc}
     */
    public function guessMissingFieldOptions($guesser, $class, $property, $options)
    {
        if (!isset($options['field_type']) && ($typeGuess = $guesser->guessType($class, $property))) {
            $options += $typeGuess->getOptions();
            $options['field_type'] = $typeGuess->getType();
        }

        if (Kernel::VERSION_ID > '20512') {
            if (!isset($options['attr']['maxlength']) && ($maxLengthGuess = $guesser->guessMaxLength($class, $property))) {
                $options['attr']['maxlength'] = $maxLengthGuess->getValue();
            }
            if (!isset($options['attr']['pattern']) && ($patternGuess = $guesser->guessPattern($class, $property))) {
                $options['attr']['pattern'] = $patternGuess->getValue();
            }
        } else {
            if (!isset($options['max_length']) && ($maxLengthGuess = $guesser->guessMaxLength($class, $property))) {
                $options['max_length'] = $maxLengthGuess->getValue();
            }
            if (!isset($options['pattern']) && ($patternGuess = $guesser->guessPattern($class, $property))) {
                $options['pattern'] = $patternGuess->getValue();
            }
        }

        return $options;
    }
}

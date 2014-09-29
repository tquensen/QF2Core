<?php

namespace QF\Form\Element;

use \QF\Form\Form;

class EmbeddedForm extends Element
{

    protected $type = 'embeddedForm';

    public function __construct($name = false, $options = array(), $validators = array())
    {
        parent::__construct($name, $options, $validators);
        if (!isset($this->options['embeddedForm'])) {
            $this->options['embeddedForm'] = new Form();
        }
    }

    public function setForm(Form $form)
    {
        parent::setForm($form);
        $this->embeddedForm->setName($this->getForm()->getName() . '[' . $this->getName() . ']');
        $this->embeddedForm->setOption('useFormToken', false);
        $this->embeddedForm->setOption('requiredMark', $form->getOption('requiredMark'));
        $this->embeddedForm->setOption('forceGlobalErrors', $form->getOption('forceGlobalErrors'));
        $this->embeddedForm->setOption('wrapper', $form->getOption('wrapper'));
        
        if ($this->getOption('useEntity') !== false && ($entity = $form->getEntity())) {
            $property = $this->getOption('entityProperty') ? $this->getOption('entityProperty') : $this->name;
            $this->embeddedForm->setEntity($entity->$property);
        }
        
        foreach ($this->embeddedForm->getElements() as $elem) {
            //refresh elements
            $elem->setForm($this->embeddedForm);
        }
    }
    
    public function setValue($value)
    {
        if ($value !== null && !$this->alwaysUseDefault) {
            $this->value = $value;
        }
        $this->embeddedForm->bindValues($value);
    }
    
    public function validate()
    {
        $embeddedFormIsValid = $this->embeddedForm->validate();
        if ($embeddedFormIsValid) {
            return parent::validate();
        } else {
            $this->isValid = false;

            if ($this->globalErrors) {
                $this->getForm()->setError($this->errorMessage, $this->getName());
            }
            
            return false;
        }
    }
    
    public function updateEntity($entity)
    {
        if ($this->getOption('useEntity') !== false && $entity) {
            $property = $this->getOption('entityProperty') ? $this->getOption('entityProperty') : $this->name;
            $entity->$property = $this->embeddedForm->updateEntity();
        }
    }

    public function toArray($public = true)
    {
        $element = parent::toArray($public);
        $element['value'] = $this->embeddedForm->toArray($public);
        return $element;
    }

}

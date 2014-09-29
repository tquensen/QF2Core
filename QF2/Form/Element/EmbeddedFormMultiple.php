<?php

namespace QF\Form\Element;

use \QF\Form\Form;

class EmbeddedFormMultiple extends Element
{

    protected $type = 'embeddedFormMultiple';
    
    protected $embeddedForms = array();

    public function __construct($name = false, $options = array(), $validators = array())
    {
        parent::__construct($name, $options, $validators);
        if (!isset($this->options['formClass'])) {
            $this->options['formClass'] = '\QF\Form\Form';
        }
        if (!isset($this->options['entityClass'])) {
            $this->options['entityClass'] = 'stdClass';
        }
        if (!isset($this->options['entityIdentifier'])) {
            $this->options['entityIdentifier'] = false;
        }
        if (!isset($this->options['entityCollectionClass'])) {
            $this->options['entityCollectionClass'] = false; //false = array(), string = classname implementing array access and traversable
        }
        if (!isset($this->options['entities'])) {
            $this->options['entities'] = false;
        }
        if (!isset($this->options['allowAdd'])) {
            $this->options['allowAdd'] = true;
        }
        if (!isset($this->options['allowDelete'])) {
            $this->options['allowDelete'] = true;
        }
        if (!isset($this->options['allowUpdate'])) {
            $this->options['allowUpdate'] = true;
        }
    }
    
    public function setForm(Form $form)
    {
        parent::setForm($form);
        
        if ($this->getOption('useEntity') !== false && ($entity = $form->getEntity())) {
            $property = $this->getOption('entityProperty') ? $this->getOption('entityProperty') : $this->name;
            $this->entities = $entity->$property;
        }
        $this->initializeEmbeddedFormsByEntities();
    }

    public function setValue($value)
    {
        if ($value !== null && !$this->alwaysUseDefault) {
            $this->value = $value;
            $this->initializeEmbeddedFormsBySubmittedValues();
        }
    }
    
    protected function initializeEmbeddedFormsByEntities()
    {
        $defaultEntities = $this->entities;
        $formClass = $this->getOption('formClass');
        $entityClass = $this->getOption('entityClass');
        $identifier = $this->getOption('entityIdentifier');
        
        if (empty($defaultEntities)) {
            return;
        }
        
        foreach ($defaultEntities as $i => $entity) {
            $form = new $formClass(array(
                'name' => $this->getForm()->getName() . '[' . $this->getName() . '][' . $i . ']',
                'entity' => $entity,
                'useFormToken' => false,
                'requiredMark' => $this->getForm()->getOption('requiredMark'),
                'forceGlobalErrors' => $this->getForm()->getOption('forceGlobalErrors'),
                'wrapper' => $this->getForm()->getOption('wrapper')
            ));
            if ($identifier) {
                $form->setElement(new \QF\Form\Element\Hidden('_identifier', array('defaultValue' => $entity->$identifier, 'useEntity' => false, 'alwaysUseDefault' => true)));
            }
            if ($this->getOption('allowAdd')) {
                $form->setElement(new \QF\Form\Element\Hidden('_new', array('defaultValue' => '0', 'useEntity' => false)));
            }
            if ($this->getOption('allowDelete')) {
                $form->setElement(new \QF\Form\Element\Checkbox('_deleted', array('defaultValue' => '0', 'useEntity' => false)));
            }
            $this->embeddedForms[$i] = $form;
        }
        
    }
    
    protected function initializeEmbeddedFormsBySubmittedValues()
    {
        $defaultEntities = $this->defaultEntities;
        $submittedValues = $this->value;
        $formClass = $this->getOption('formClass');
        $entityClass = $this->getOption('entityClass');
        $identifier = $this->getOption('entityIdentifier');
        
        $allowAdd = $this->getOption('allowAdd');
        $allowUpdate = $this->getOption('allowUpdate');
        
        if ($allowUpdate) {
            //update existing forms/entities
            foreach ($submittedValues as $i => $values) {
                //if not new, check if valid entity exists
                if (empty($values['_new'])) {
                    $found = false;
                    if ($identifier) {
                        if (!empty($values['_identifier'])) {
                            foreach ($defaultEntities as $j => $entity) {
                                if ($entity->$identifier == $values['_identifier']) {
                                    $found = true;
                                    $key = $j;
                                    break;
                                }
                            }
                        }
                    } else {
                        if (!empty($defaultEntities[$i])) {
                            $found = true;
                            $key = $i;
                        }
                    }
                    if ($found) {
                        $this->embeddedForms[$key]->bindValues($values);
                    } else {
                        //invalid entity (deleted from another request) - remove form
                    }
                }
            }
        }
        
        if ($allowAdd) {
            //add new entities
            foreach ($submittedValues as $i => $values) {
                //ignore new and deleted
                if (!empty($values['_new']) && !empty($values['_deleted'])) {
                    continue;
                }
                //if new, create new form
                if (!empty($values['_new'])) {
                    $form = new $formClass(array(
                        'name' => $this->getForm()->getName() . '[' . $this->getName() . '][' . $i . ']',
                        'entity' => new $entityClass,
                        'useFormToken' => false,
                        'requiredMark' => $this->getForm()->getOption('requiredMark'),
                        'forceGlobalErrors' => $this->getForm()->getOption('forceGlobalErrors'),
                        'wrapper' => $this->getForm()->getOption('wrapper')
                    ));
                    $form->setElement(new \QF\Form\Element\Hidden('_new', array('defaultValue' => '1', 'useEntity' => false)));
                    $form->setElement(new \QF\Form\Element\Checkbox('_deleted', array('defaultValue' => '0', 'useEntity' => false)));
                    $form->bindValues($values);
                    $this->embeddedForms[] = $form;
                }
            }
        }
        //$this->embeddedForm->bindValues($value);
    }
    
    public function getEmbeddedForms()
    {
        //make sure to use correct name and options
        foreach ($this->embeddedForms as $i => $embeddedForm) {
            $embeddedForm->setName($this->getForm()->getName() . '[' . $this->getName() . '][' . $i . ']');
            $embeddedForm->setOption('useFormToken', false);
            $embeddedForm->setOption('requiredMark', $form->getOption('requiredMark'));
            $embeddedForm->setOption('forceGlobalErrors', $form->getOption('forceGlobalErrors'));
            $embeddedForm->setOption('wrapper', $form->getOption('wrapper'));
            foreach ($embeddedForm->getElements() as $elem) {
                //refresh elements
                $elem->setForm($embeddedForm);
            }
        }
        return $this->embeddedForms;
    }
    
    public function getEntities()
    {
        $allowAdd = $this->getOption('allowAdd');
        $allowUpdate = $this->getOption('allowUpdate');
        $allowDelete = $this->getOption('allowDelete');
        
        $entities = array('new' => array(), 'updated' => array(), 'deleted' => array(), 'unchanged' => array());
        foreach ($this->getEmbeddedForms() as $i => $form) {
            if ($form->_new && $form->_new->value) {
                if ($allowAdd) {
                    $entities['new'][] = $form->updateEntity();
                }
            } elseif ($form->_deleted && $form->_deleted->value) {
                if ($allowDelete) {
                    $entities['deleted'][] = $form->updateEntity();
                }
            } else {
                if ($allowUpdate) {
                    $entities['updated'][] = $form->updateEntity();
                } else {
                    $entities['unchanged'][] = $form->getEntity();
                }
            }
        }
        return $entities;
    }
    
    public function validate()
    {
        foreach ($this->embeddedForms as $i => $form) {
            if (!$form->validate()) {
                $this->isValid = false; 
            }
        }
        if ($this->isValid) {
            return parent::validate();
        } else {
            if ($this->globalErrors) {
                $this->getForm()->setError($this->errorMessage, $this->getName());
            }
        }
    }
    
    public function updateEntity($entity)
    {
        if ($this->getOption('useEntity') !== false && $entity) {
            $entityCollectionClass = $this->getOption('entityCollectionClasss');
            $entities = $entityCollectionClass ? new $entityCollectionClass() : array();
            
            $allowAdd = $this->getOption('allowAdd');
            $allowUpdate = $this->getOption('allowUpdate');
            $allowDelete = $this->getOption('allowDelete');
            
            foreach ($this->embeddedForms as $form) {
               if ($form->_new && $form->_new->value) {
                    if ($allowAdd) {
                        $entities[] = $form->updateEntity();
                    }
                } elseif ($form->_deleted && $form->_deleted->value) {
                    if (!$allowDelete) {
                        $entities[] = $form->updateEntity();
                    }
                } else {
                    if ($allowUpdate) {
                        $entities[] = $form->updateEntity();
                    } else {
                        $entities[] = $form->getEntity();
                    }
                }
            }
            
            $property = $this->getOption('entityProperty') ? $this->getOption('entityProperty') : $this->name;
            $entity->$property = $entities;
        }
    }

    public function toArray($public = true)
    {
        $element = parent::toArray($public);
        $values = array();
        foreach ($this->getEmbeddedForms() as $i => $form) {
            $values[$i] = $form->toArray($public);
            
        }
        $element['value'] = $values;
        $element['entityIdentifier'] = $this->getOption('entityIdentifier');
        $element['allowAdd'] = $this->getOption('allowAdd');
        $element['allowUpdate'] = $this->getOption('allowUpdate');
        $element['allowDelete'] = $this->getOption('allowDelete');
        return $element;
    }

}

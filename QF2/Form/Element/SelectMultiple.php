<?php

namespace QF\Form\Element;

use \QF\Form\Validator\InArray;

class SelectMultiple extends Element
{

    protected $type = 'selectMultiple';

    public function __construct($name = false, $options = array(), $validators = array())
    {
        parent::__construct($name, $options, $validators);
        if (!isset($this->options['options'])) {
            $this->options['options'] = array();
        }
    }

    public function setValue($value)
    {
        parent::setValue($value);
        if (!is_array($this->value)) {
            $this->value = (array) $this->value;
        }
    }

    public function validate()
    {
        if ($this->value && !$this->getOption('skipDefaultValidator')) {
            $values = array();
            foreach ($this->options['options'] as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $k => $v) {
                        $values[] = $k;
                    }
                } else {
                    $values[] = $key;
                }
            }
            $this->validators[] = new InArray(array('errorMessage' => $this->errorMessage, 'array' => $values, 'multiple' => true));
        }

        return parent::validate();
    }

    public function toArray($public = true)
    {
        $element = parent::toArray($public);
        $element['fullName'] = $element['fullName'] . '[]';
        if ($public) {
            $element['elements'] = $this->options['options'];
            if ($this->options['size']) {
                $element['options']['size'] = $this->options['size'];
            }
        }
        return $element;
    }

}

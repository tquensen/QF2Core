<?php

namespace QF\Form\Element;

use \QF\Form\Validator\InArray;

class Radio extends Element
{

    protected $type = 'radio';

    public function __construct($name = false, $options = array(), $validators = array())
    {
        parent::__construct($name, $options, $validators);
        if (!isset($this->options['elements'])) {
            $this->options['elements'] = array();
        }
    }

    public function validate()
    {
        if ($this->value && !$this->getOption('skipDefaultValidator')) {
            $values = array();
            foreach ($this->options['elements'] as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $k => $v) {
                        $values[] = $k;
                    }
                } else {
                    $values[] = $key;
                }
            }
            $this->validators[] = new InArray(array('errorMessage' => $this->errorMessage, 'array' => $values));
        }

        return parent::validate();
    }

    public function toArray($public = true)
    {
        $element = parent::toArray($public);
        $element['fullName'] = $element['fullName'] . '[]';
        if ($public) {
            $element['elements'] = $this->options['elements'];
        }
        return $element;
    }

}

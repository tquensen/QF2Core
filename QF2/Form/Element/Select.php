<?php
namespace QF\Form\Element;

use \QF\Form\Validator\InArray;

class Select extends Element
{
	protected $type = 'select';

	public function __construct($name = false, $options = array(), $validators = array())
	{
		parent::__construct($name, $options, $validators);
		if (!isset($this->options['options']))
		{
			$this->options['options'] = array();
		}
	}

	public function validate()
	{
		if (!$this->getOption('skipDefaultValidator'))
		{
            $values = array();
            foreach ($this->options['options'] as $key => $value) {
                if (is_array($value)) {
                    foreach($value as $k => $v) {
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
        if ($public) {
            $element['elements'] = $this->options['options'];
        }
        return $element;
    }
}
<?php
namespace QF\Form\Element;

class Fieldset extends Element
{
	protected $type = 'fieldset';

    public function setValue($value)
	{
	}

	public function updateEntity($entity)
	{
	}

    public function toArray($public = true)
    {
        $element = parent::toArray($public);
        if ($public) {
            $element['options']['legend'] = $this->legend;
        }
        return $element;
    }
}
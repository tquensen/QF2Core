<?php
namespace QF\Form;

use \QF\Form\Element\Element;
use \QF\Form\Validator\Validator;

class Form
{
    protected $elements = array();
    protected $name = null;
    protected $isValid = true;
    protected $wasSubmitted = null;
    protected $errors = array();
    protected $options = array();
    protected $entity = null;
    protected $postValidators = array();

    public function __construct($options = array())
    {
        $this->name = (isset($options['name'])) ? $options['name'] : $this->name;
        $this->entity = (isset($options['entity'])) ? $options['entity'] : $this->entity;

        $this->options['method'] = 'POST';
        $this->options['action'] = !empty($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $this->options['wrapper'] = 'div';
        $this->options['showGlobalErrors'] = true;
        $this->options['forceGlobalErrors'] = false;
        $this->options['requiredMark'] = ' * ';
        $this->options['useFormToken'] = true;
        $this->options['uniqueFormToken'] = false;
        $this->options['maxAgeFormToken'] = false;
        $this->options['formTokenName'] = $this->name.'__form_token';
        $this->options['formTokenErrorMessage'] = 'invalid Form Token';
        $this->options = array_merge($this->options, (array)$options);
    }

    public function getFormToken()
    {
        if (empty($this->options['useFormToken']) || empty($this->options['formTokenName'])) {
            return false;
        }
        
        if ($this->options['uniqueFormToken'] || empty($_SESSION['_QF_FORM_TOKEN'][$this->options['formTokenName']])) {
            $token = time().'|'.md5(rand(10000, 99999));
            $_SESSION['_QF_FORM_TOKEN'][$this->options['formTokenName']] = $token;
        } else {
            $token = $_SESSION['_QF_FORM_TOKEN'][$this->options['formTokenName']];
        }
        return $token;
    }

    public function checkFormToken()
    {
        if (empty($this->options['useFormToken']) || empty($this->options['formTokenName']) || empty($_REQUEST[$this->options['formTokenName']])) {
            return false;
        }
        $token = $_REQUEST[$this->options['formTokenName']];
        if (!empty($_SESSION['_QF_FORM_TOKEN'][$this->options['formTokenName']]) && $_SESSION['_QF_FORM_TOKEN'][$this->options['formTokenName']] == $token) {
            if ($this->options['uniqueFormToken']) {
                unset($_SESSION['_QF_FORM_TOKEN'][$this->options['formTokenName']]);
            }
            if ($this->options['maxAgeFormToken']) {
                $age = explode('|', $token, 2);
                $age = (int) $age[0];
                if (time() - $age > $this->options['maxAgeFormToken']) {
                    return false;
                }
            }
            return true;
        } else {
            return false;
        }
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getEntity()
    {
        return $this->entity;
    }

    public function setEntity($entity)
    {
        $this->entity = $entity;
    }

    public function updateEntity()
    {
        if (!is_object($this->entity)) {
            return false;
        }
        foreach ($this->elements as $element) {
            $element->updateEntity($this->entity);
        }
        return $this->entity;
    }

    public function __get($element)
    {
        return $this->getElement($element);
    }

    public function setOption($option, $value)
    {
        $this->options[$option] = $value;
        return true;
    }

    public function getOption($option)
    {
        return (isset($this->options[$option])) ? $this->options[$option] : null;
    }

    public function setElement(Element $element)
    {
        $this->elements[$element->getName()] = $element;
        $element->setForm($this);
        return $this;
    }

    /**
     *
     * @param string $name the name of a form element
     * @return \QF\Form\Element\Element
     */
    public function getElement($name)
    {
        return (isset($this->elements[$name])) ? $this->elements[$name] : null;
    }

    public function getElements()
    {
        return $this->elements;
    }

    public function setPostValidator(Validator $validator)
    {
        $this->postValidators[] = $validator;
        $validator->setForm($this);
        return $this;
    }

    public function getPostValidators()
    {
        return $this->postValidators;
    }

    public function bindValues($values = null)
    {
        if ($values === null) {
            $arr = strtoupper($this->getOption('method')) === 'GET' ? $_GET : $_POST;
            if (empty($arr[$this->name])) {
                $this->wasSubmitted = false;
                return false;
            }
            $values = $arr[$this->name];
        }

        $this->wasSubmitted = true;
        
        foreach ($this->elements as $element) {
            $element->setValue(isset($values[$element->getName()]) ? $values[$element->getName()]
                                : false);
        }
        return true;
    }

    public function setError($errorMessage = null, $element = null)
    {
        $this->isValid = false;
        if ($errorMessage) {
            $this->errors[] = array('message' => $errorMessage, 'element' => $element);
        }
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function hasErrors()
    {
        return (bool) count($this->errors);
    }

    public function isValid()
    {
        return $this->isValid;
    }

    public function validate($values = null)
    {
        if ($values !== null) {
            $this->bindValues($values);
        }
        
        if (!$this->wasSubmitted()) {
            return false;
        }

        if ($this->getOption('useFormToken') && !$this->checkFormToken()) {
            $this->setError($this->getOption('formTokenErrorMessage'));
        }
        
        foreach ($this->elements as $element) {
            if (!$element->validate()) {
                $this->isValid = false;
            }
        }

        foreach ($this->postValidators as $validator)
		{
			if (!$validator->validate($this->isValid))
			{
                $errorMessage = $validator->errorMessage;
				if ($errorMessage)
				{
					$this->setError($errorMessage);
				}
				$this->isValid = false;
			}
		}

        return $this->isValid;
    }

    public function wasSubmitted()
    {
        if ($this->wasSubmitted === null) {
            $this->bindValues();
        }
        return $this->wasSubmitted;
    }

    public function getValues()
    {
        $values = array();
        foreach ($this->elements as $element) {
            $values[$element->getName()] = $element->value;
        }
        return $values;
    }

    /**
     *
     * @param bool $public whether to export only "save" data (true, default) or any options of the form (false)
     * @return array the array representation of this form
     */
    public function toArray($public = true)
    {
        $form = array();
        $form['name'] = $this->name;
        if ($public) {
            $form['action'] = $this->getOption('action');
            $form['method'] = $this->getOption('method');
            $form['enctype'] = $this->getOption('enctype');
        }
        
        $form['globalErrors'] = $this->errors;
        $form['elements'] = array();
        foreach ($this->elements as $element) {
            $elementData = $element->toArray($public);
            if ($elementData) {
                $form['elements'][$element->getName()]  = $elementData;
            }
        }
        if ($public) {
            $form['options'] = array();
            $form['options']['showGlobalErrors'] = $this->getOption('showGlobalErrors');
            $form['options']['requiredMark'] = $this->getOption('requiredMark');

            $form['options']['class'] = $this->getOption('class');
            $form['options']['attributes'] = $this->getOption('attributes');
        } else {
            $form['options'] = $this->options;
        }
        $form['isValid'] = $this->isValid;
        $form['wasSubmitted'] = $this->wasSubmitted();
        if ($this->getOption('useFormToken')) {
            $form['auth']['formToken'] = $this->getFormToken();
            $form['auth']['formTokenName'] = $this->getOption('formTokenName');
        }


        return $form;
    }
}
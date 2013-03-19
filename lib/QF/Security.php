<?php
namespace QF;

class Security
{
    protected $roles = array();
    
    protected $attributes = array();
    
    protected $user = null;
    protected $role = 'GUEST';

    public function __construct($roles)
    {
        $this->roles = $roles;
        
        if (isset($_SESSION['_QF_USER'])) {
            $this->role = $_SESSION['_QF_USER']['role'];
            $this->user = $_SESSION['_QF_USER']['user'];
            $this->attributes = isset($_SESSION['_QF_USER']['attributes']) ? $_SESSION['_QF_USER']['attributes'] : array();
        }
    }
    
    public function login($role, $user = null, $persistent = false) {
        $this->setRole($role, $persistent);
        $this->setUser($user, $persistent);
    }
    
    public function logout($clearAttributes = false, $persistent = true) {
        $this->setRole('GUEST', $persistent);
        $this->setUser(null, $persistent);
        if ($clearAttributes) {
            $this->setAttributes(array(), true);
        }
    }
    
    public function getUser()
    {
        return $this->user;
    }
    
    public function getRole() {
        return $this->role;
    }
    
    public function getAttributes() {
        return $this->attributes;
    }
    
    public function getAttribute($key) {
        return isset($this->attributes[$key]) ? $this->attributes[$key] : null;
    }
    
    public function setUser($user, $persistent = false) {
        $this->user = $user;
        if ($persistent) {
            $_SESSION['_QF_USER']['user'] = $user;
        }
    }

    public function setRole($role, $persistent = false) {
        $this->role = $role;
        if ($persistent) {
            $_SESSION['_QF_USER']['role'] = $role;
        }
    }
    
    public function setAttributes($attributes, $persistent = false) {
        $this->attributes = (array) $attributes;
        if ($persistent) {
            $_SESSION['_QF_USER']['attributes'] = (array) $attributes;
        }
    }
    
    public function setAttribute($key, $value, $persistent = false) {
        $this->attributes[$key] = $value;
        if ($persistent) {
            $_SESSION['_QF_USER']['attributes'][$key] = $value;
        }
    }
    
    /**
     *
     * @param string|array $rights the name of the right as string ('user', 'administrator', ..) or as array of rights
     * @return bool whether the current user has the required right or not / returns true if the right is 0
     */
    public function userHasRight($rights)
    {
        if (!$rights) {
            return true;
        }
        
        if (empty($this->roles[$this->role])) {
            return false;
        }
        
        return $this->checkRights($this->roles[$this->role], $rights);
    }
    
    /**
     *
     * @param string $role the role to check
     * @param string|array $rights the name of the right as string ('user', 'administrator', ..) or as array of rights
     * @return bool whether the current user has the required right or not / returns true if the right is 0
     */
    public function roleHasRight($role, $rights)
    {
        if (!$rights) {
            return true;
        }
        
        if (empty($this->roles[$role])) {
            return false;
        }
        
        return $this->checkRights($this->roles[$role], $rights);
    }
    
    protected function checkRights($givenRights, $requiredRights, $and = true)
    {
        if ($and) {
            foreach ((array)$requiredRights as $right) {
                if (is_array($right)) {
                    if (!$this->checkRights($givenRights, $right, !$and)) {
                        return false;
                    }
                }
                if (!in_array($right, (array) $givenRights)) {
                    return false;
                }
            }
            return true;
        } else {
            foreach ((array)$requiredRights as $right) {
                if (is_array($right)) {
                    if ($this->checkRights($givenRights, $right, !$and)) {
                        return true;
                    }
                }
                if (in_array($right, (array) $givenRights)) {
                    return true;
                }
            }
            return false;
        }
    }
    
    public function generateFormToken($name = true)
    {
        $token = md5(time().rand(10000, 99999));
        $_SESSION['_QF_FORM_TOKEN'][$token] = $name;
        return $token;
    }

    public function checkFormToken($name = 'form_token')
    {
        $token = !empty($_REQUEST[$name]) ? $_REQUEST[$name] : false;
        if (!empty($token) && !empty($_SESSION['_QF_FORM_TOKEN'][$token]) && $_SESSION['_QF_FORM_TOKEN'][$token] == $name) {
            unset($_SESSION['_QF_FORM_TOKEN'][$token]);
            return true;
        } else {
            return false;
        }
    }
}
<?php

/////////////////////////////////////////////////////////////////////////////////////////////////////

namespace Payhub;

/////////////////////////////////////////////////////////////////////////////////////////////////////

class ORDER extends MAGENTOAbstract
{
    /////////////////////////////////////////////////////////////////////////////////////////////////////

    public $increment_id;
    public $customer;
    public $billing_address;
    public $shipping_address;

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    public function __construct($entity = null) {
        parent::__construct('sales/order');

        if (is_null($entity) == false) { 
            $this->from_entity($entity);
        }
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    public function from_entity($entity) { 
        parent::load($entity);

        $this->increment_id = $entity->getIncrementId();
        $this->customer = new CUSTOMER();
        $this->customer->from_quote($entity->getQuote());
        $this->billing_address = new ADDRESS($entity->getQuote()->getBillingAddress(), ADDRESS::BILLING);
        $this->shipping_address = new ADDRESS($entity->getQuote()->getShippingAddress(), ADDRESS::SHIPPING);

        return $this;
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////

}

/////////////////////////////////////////////////////////////////////////////////////////////////////

class MAGENTOAbstract 
{
    /////////////////////////////////////////////////////////////////////////////////////////////////////

    public $id;
    public $code;
    public $entity;

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    public function __construct($code) { 
        $this->code = $code;
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    public function load(& $entity = null) { 
        if (! $entity) { 

        } else if (is_numeric($entity)) { 
            $this->entity = $entity = \Mage::getModel($this->code)->load($entity);

            $this->id = $this->entity->getId();
        } else if ($entity instanceof MAGENTOAbstract) { 
            $this->id = $entity->id;
        } else { 
            $this->entity = $entity;
            $this->id = $entity->getId();
        }

        return $this;
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    public function refresh() { 
        if ($this->entity) { 
            $this->from_entity($this->entity);
        }
    }

    ///////////////////////////////////////////////////////////////////////////////////////////////////

}

/////////////////////////////////////////////////////////////////////////////////////////////////////

class CUSTOMER extends MAGENTOAbstract 
{
    /////////////////////////////////////////////////////////////////////////////////////////////////////

    public $id;
    public $first_name;
    public $last_name;
    public $email;

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    public function __construct($data = null) { 
        parent::__construct('customer/customer');

        if (is_null($data) == false) { 
            $this->from_entity($data);
        }
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    public function from_entity($mage_customer) { 
        $this->load($mage_customer);

        $data = $mage_customer->getData();

        $this->email = get_and_require($data, 'email');
        $this->first_name = get_or_null($data, 'firstname');
        if (empty($this->first_name)) { 
            $mage_customer->load();
            $data = $mage_customer->getData();
            $this->first_name = get_or_null($data, 'firstname');
        }
        $this->last_name = get_or_null($data, 'lastname');

        return $this;
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    public function from_quote($mage_quote) { 
        $this->email = $mage_quote->getCustomerEmail();
        $this->first_name = $mage_quote->getCustomerFirstname();
        $this->last_name = $mage_quote->getCustomerLastname();
        return $this;
    }

    ///////////////////////////////////////////////////////////////////////////////////////////////////

}

/////////////////////////////////////////////////////////////////////////////////////////////////////

class ADDRESS extends MAGENTOAbstract 
{
    /////////////////////////////////////////////////////////////////////////////////////////////////////

    const BILLING = 1;
    const SHIPPING = 2;

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    public $first_name;
    public $last_name;
    public $name;
    public $street1;
    public $street2;
    public $postcode;
    public $city;
    public $state;
    public $country_id;
    public $phone;
    public $address_type;
    public $is_default;

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    public function __construct($data = null, $type = null, $default = null) { 
        parent::__construct('customer/address');

        if (is_null($type) == false) { 
            $this->from_entity($data, $type, $default);
        }
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////

    public function from_entity($mage_address, $type, $default = null) {
        $this->load($mage_address);

        $this->is_default = $default;

        $data = $mage_address->getData();

        $this->address_type = $type;

        $this->first_name = get_or_null($data, 'firstname');
        $this->last_name = get_or_null($data, 'lastname');
        $this->name = $this->first_name . ' ' . $this->last_name;
        $street = $mage_address->getStreet();

        $this->street1 = array_shift($street);
        if ($street) { 
            $this->street2 = implode(' ', $street);
        }
        if ($this->street1) { 
            $this->street2 = trim(substr($this->street1, 30) . ' ' . $this->street2);
            $this->street1 = substr($this->street1, 0, 30);
        }

        $this->postcode = get_or_null($data, 'postcode');
        $this->city = get_or_null($data, 'city');
        $this->country_id = get_or_null($data, 'country_id');
        $this->state = get_or_null($data, 'region');
        $this->phone = preg_replace('/[^0-9]/', '', get_or_null($data, 'telephone'));

        return $this;
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////
}

/////////////////////////////////////////////////////////////////////////////////////////////////////


<?php
/**
 * UserModel - Model for user data in the Blackwall module
 */

class UserModel
{
    private $id;
    private $email;
    private $first_name;
    private $last_name;
    private $api_key;
    private $tags = [];
    private $created_at;
    private $updated_at;
    
    /**
     * Constructor
     * 
     * @param string $email User email
     * @param string $first_name User first name
     * @param string $last_name User last name
     * @param string $id User ID (optional)
     * @param string $api_key User API key (optional)
     */
    public function __construct($email, $first_name, $last_name, $id = null, $api_key = null)
    {
        $this->email = $email;
        $this->first_name = $first_name;
        $this->last_name = $last_name;
        $this->id = $id;
        $this->api_key = $api_key;
        $this->created_at = time();
        $this->updated_at = time();
    }
    
    /**
     * Get ID
     * 
     * @return string User ID
     */
    public function getId()
    {
        return $this->id;
    }
    
    /**
     * Set ID
     * 
     * @param string $id User ID
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }
    
    /**
     * Get email
     * 
     * @return string User email
     */
    public function getEmail()
    {
        return $this->email;
    }
    
    /**
     * Set email
     * 
     * @param string $email User email
     * @return $this
     */
    public function setEmail($email)
    {
        $this->email = $email;
        $this->updated_at = time();
        return $this;
    }
    
    /**
     * Get first name
     * 
     * @return string User first name
     */
    public function getFirstName()
    {
        return $this->first_name;
    }
    
    /**
     * Set first name
     * 
     * @param string $first_name User first name
     * @return $this
     */
    public function setFirstName($first_name)
    {
        $this->first_name = $first_name;
        $this->updated_at = time();
        return $this;
    }
    
    /**
     * Get last name
     * 
     * @return string User last name
     */
    public function getLastName()
    {
        return $this->last_name;
    }
    
    /**
     * Set last name
     * 
     * @param string $last_name User last name
     * @return $this
     */
    public function setLastName($last_name)
    {
        $this->last_name = $last_name;
        $this->updated_at = time();
        return $this;
    }
    
    /**
     * Get full name
     * 
     * @return string User full name
     */
    public function getFullName()
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
    
    /**
     * Get API key
     * 
     * @return string User API key
     */
    public function getApiKey()
    {
        return $this->api_key;
    }
    
    /**
     * Set API key
     * 
     * @param string $api_key User API key
     * @return $this
     */
    public function setApiKey($api_key)
    {
        $this->api_key = $api_key;
        $this->updated_at = time();
        return $this;
    }
    
    /**
     * Get tags
     * 
     * @return array User tags
     */
    public function getTags()
    {
        return $this->tags;
    }
    
    /**
     * Set tags
     * 
     * @param array $tags User tags
     * @return $this
     */
    public function setTags(array $tags)
    {
        $this->tags = $tags;
        $this->updated_at = time();
        return $this;
    }
    
    /**
     * Add tag
     * 
     * @param string $tag User tag
     * @return $this
     */
    public function addTag($tag)
    {
        if (!in_array($tag, $this->tags)) {
            $this->tags[] = $tag;
            $this->updated_at = time();
        }
        return $this;
    }
    
    /**
     * Remove tag
     * 
     * @param string $tag User tag
     * @return $this
     */
    public function removeTag($tag)
    {
        $key = array_search($tag, $this->tags);
        if ($key !== false) {
            unset($this->tags[$key]);
            $this->tags = array_values($this->tags);
            $this->updated_at = time();
        }
        return $this;
    }
    
    /**
     * Has tag
     * 
     * @param string $tag User tag
     * @return bool True if user has tag
     */
    public function hasTag($tag)
    {
        return in_array($tag, $this->tags);
    }
    
    /**
     * Get created at timestamp
     * 
     * @return int Created at timestamp
     */
    public function getCreatedAt()
    {
        return $this->created_at;
    }
    
    /**
     * Get updated at timestamp
     * 
     * @return int Updated at timestamp
     */
    public function getUpdatedAt()
    {
        return $this->updated_at;
    }
    
    /**
     * Convert to array
     * 
     * @return array Model data as array
     */
    public function toArray()
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'api_key' => $this->api_key,
            'tags' => $this->tags,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
    
    /**
     * Convert to Botguard API format
     * 
     * @return array Model data in Botguard API format
     */
    public function toBotguardFormat()
    {
        return [
            'email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name
        ];
    }
    
    /**
     * Convert to GateKeeper API format
     * 
     * @return array Model data in GateKeeper API format
     */
    public function toGatekeeperFormat()
    {
        return [
            'id' => $this->id,
            'tag' => $this->tags
        ];
    }
    
    /**
     * Create from array
     * 
     * @param array $data Model data
     * @return static New instance
     */
    public static function fromArray(array $data)
    {
        $model = new static(
            $data['email'], 
            $data['first_name'], 
            $data['last_name'], 
            $data['id'] ?? null, 
            $data['api_key'] ?? null
        );
        
        if (isset($data['tags']) && is_array($data['tags'])) {
            $model->setTags($data['tags']);
        }
        
        if (isset($data['created_at'])) {
            $model->created_at = $data['created_at'];
        }
        
        if (isset($data['updated_at'])) {
            $model->updated_at = $data['updated_at'];
        }
        
        return $model;
    }
    
    /**
     * Create from Botguard API response
     * 
     * @param array $data Botguard API response data
     * @return static New instance
     */
    public static function fromBotguardResponse(array $data)
    {
        $model = new static(
            $data['email'],
            $data['first_name'],
            $data['last_name'],
            $data['id'] ?? null,
            $data['api_key'] ?? null
        );
        
        if (isset($data['created_at'])) {
            // Convert from API timestamp format if needed
            $model->created_at = is_numeric($data['created_at']) ? $data['created_at'] : strtotime($data['created_at']);
        }
        
        if (isset($data['updated_at'])) {
            // Convert from API timestamp format if needed
            $model->updated_at = is_numeric($data['updated_at']) ? $data['updated_at'] : strtotime($data['updated_at']);
        }
        
        return $model;
    }
}

<?php

namespace lx\socket\serverTools;

/**
 * Class OriginValidator
 * @package lx\socket
 */
class OriginValidator
{
    /** @var bool */
    private $checkOrigin = true;

    /** @var array */
    private $allowedOrigins = [];

    /**
     * OriginValidator constructor.
     * @param array $config
     */
    public function __construct($config = [])
    {
        if (array_key_exists('checkOrigin', $config)) {
            $this->checkOrigin = (bool)$config['checkOrigin'];
        }

        if (array_key_exists('allowedOrigins', $config)) {
            foreach ((array)$config['allowedOrigins'] as $allowedOrigin) {
                $this->setAllowedOrigin($allowedOrigin);
            }
        }
    }

    /**
     * @return bool
     */
    public function needValidate() : bool
    {
        return $this->checkOrigin;
    }

    /**
     * @param string $domain
     * @return bool
     */
    public function validate(string $domain) : bool
    {
        $domain = $this->clearDomain($domain);
        return array_key_exists($domain, $this->allowedOrigins) && $this->allowedOrigins[$domain];
    }

    /**
     * @param string $domain
     */
    private function setAllowedOrigin(string $domain) : void
    {
        $domain = $this->clearDomain($domain);
        if (empty($domain)) {
            return;
        }

        $this->allowedOrigins[$domain] = true;
    }

    /**
     * @param string $domain
     * @return string
     */
    private function clearDomain(string $domain) : string
    {
        $domain = str_replace('https://', '', $domain);
        $domain = str_replace('http://', '', $domain);
        $domain = str_replace('www.', '', $domain);
        $domain = preg_replace('/^\//', '', $domain);
        $domain = (strpos($domain, '/') !== false)
            ? substr($domain, 0, strpos($domain, '/'))
            : $domain;

        return $domain;
    }
}

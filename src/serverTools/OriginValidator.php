<?php

namespace lx\socket\serverTools;

class OriginValidator
{
    private bool $checkOrigin = true;
    private array $allowedOrigins = [];

    public function __construct(array $config = [])
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

    public function needValidate() : bool
    {
        return $this->checkOrigin;
    }

    public function validate(string $domain) : bool
    {
        $domain = $this->clearDomain($domain);
        return array_key_exists($domain, $this->allowedOrigins) && $this->allowedOrigins[$domain];
    }

    private function setAllowedOrigin(string $domain) : void
    {
        $domain = $this->clearDomain($domain);
        if (empty($domain)) {
            return;
        }

        $this->allowedOrigins[$domain] = true;
    }

    private function clearDomain(string $domain) : string
    {
        $domain = str_replace('https://', '', $domain);
        $domain = str_replace('http://', '', $domain);
        $domain = str_replace('www.', '', $domain);
        $domain = preg_replace('/^\//', '', $domain);
        $domain = (strpos($domain, '/') !== false)
            ? substr($domain, 0, strpos($domain, '/'))
            : $domain;
        $domain = preg_replace('/:[\d]+$/', '', $domain);

        return $domain;
    }
}

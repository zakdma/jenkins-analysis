<?php

class SubBuild
{
    //http://10.234.207.211:8080/job/Functional-Tests-CE/2212/allure/#suites/8187718e2fd144987673ef34ee62b41b/41bca3f62db77445/
    public $version;
    public $name;
    public $url;
    public $withExtension = false;
    public $failed = false;
    public $hasAllure = false;
    public $allureUrl;

    private $suitesData;
    private $suitesItems;

    public function getSuitesUrl()
    {
        return $this->allureUrl . '/data/suites.json';
    }

    public function getSuitesData()
    {
        if (!$this->suitesData && $this->allureUrl) {
            $this->suitesData = $this->loadSuitesData();
        }

        return $this->suitesData;
    }

    public function getSuitesItems()
    {
        if (!$this->suitesItems) {
            $this->suitesItems = [];
            $this->getSuitesItemsRecursive($this->getSuitesData());
        }

        return $this->suitesItems;
    }

    private function getSuitesItemsRecursive(\stdClass $node)
    {
        if (isset($node->children)) {
            foreach ($node->children as $child) {
                $this->getSuitesItemsRecursive($child);
            }
        } else {
            if (!isset($node->status) || !isset($node->parentUid)) {
                throw new Exception('Broken node');
            }
            $item = new SuiteItem();
            $item->name = $node->name;
            $item->uid = $node->uid;
            $item->parentUid = $node->parentUid;
            $item->status = $node->status;
            $ticket = $this->extractTicket($item->name);
            if ($ticket) {
                $item->ticket = $ticket;
                $existingItem = !empty($this->suitesItems[$ticket]) ? $this->suitesItems[$ticket] : null;
                if (!empty($existingItem)) {
                    if (!is_array($existingItem)) {
                        $existingItem = [$existingItem];
                        $this->suitesItems[$ticket] = $existingItem;
                    }
                    $this->suitesItems[$ticket][] = $item;
                } else {
                    $this->suitesItems[$ticket] = $item;
                }
            }
        }
    }

    private function extractTicket($name)
    {
        $matches = [];
        //"MC-15867: Create invoice with shipment and check invoiced order test"
        if (preg_match_all('/^(?<number>(MC|MAGETWO)-\d+)?:/', $name, $matches)) {
            return $matches['number'][0];
        }

        return null;
    }

    private function loadSuitesData()
    {
        $json = getUrlContent($this->getSuitesUrl());
        //echo $json;
        $data = json_decode($json);

        return $data;
    }
}

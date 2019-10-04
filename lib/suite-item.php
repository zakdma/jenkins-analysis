<?php

class SuiteItem
{
    //http://10.234.207.211:8080/job/Functional-Tests-CE/2212/allure/#suites/8187718e2fd144987673ef34ee62b41b/41bca3f62db77445/
    public $name;
    public $uid;
    public $parentUid;
    public $status;
    public $ticket;

    public function getUrl($allureUrl)
    {
        return $allureUrl . sprintf("/#suites/%s/%s/", $this->parentUid, $this->uid);
    }
}

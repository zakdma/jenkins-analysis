<?php

function getUrlContent($url)
{
    global $jenkinsUser, $jenkinsPassword, $curlProxy, $curlProxyUser, $curlProxyPassword;
    $ch = curl_init();
    //curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_USERPWD, $jenkinsUser . ":" . $jenkinsPassword);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    //curl_setopt($ch, CURLOPT_POSTFIELDS, $request);

//    curl_setopt($ch,
//        CURLOPT_HTTPHEADER,
//        array(
//            $cookies2
//            //'Content-type: application/json;charset=UTF-8',
//            //'Accept: application/json;charset=UTF-8'
//        )
//    );
    if (!empty($curlProxy)) {
        curl_setopt($ch, CURLOPT_PROXY, $curlProxyUser);
        if (!empty($curlProxyUser) || !empty($curlProxyPassword)) {
            $proxyAuth = $curlProxyUser.':'.$curlProxyPassword;
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxyAuth);
        }
    }
    $responseBody = curl_exec($ch);
    curl_close($ch);

    return $responseBody;
}

function getFuncSubBuilds($buildUrl)
{
    $result = [];
    $body = getUrlContent($buildUrl);
    $matches = [];
    if (preg_match_all('/\<a\s+?href="(?<url>http:\/\/.+?)"\>/', $body, $matches)) {
        //var_dump($matches);
        foreach ($matches[0] as $index => $value) {
            $matchesLink = [];
            if (preg_match('/(?<name>Functional-Tests-(?<version>.+))\/(?<id>.+)/', $matches['url'][$index], $matchesLink)) {
                $item = new SubBuild();
                $item->url = rtrim($matches['url'][$index], '/') . '/';
                $item->name = $matchesLink['name'];
                $item->version = $matchesLink['version'];;
                loadFuncSubBuildInfo($item);
                $result[] = $item;
            }
        }
    }

    return $result;
}

/**
 * Робить запит на сторінку саббилда і бере з відти інформацію якої бракує
 * Чи це билд з extensions і чи він зафейлений
 *
 * @param SubBuild $subBuild
 * @return void
 */
function loadFuncSubBuildInfo(SubBuild $subBuild)
{
    $body = getUrlContent($subBuild->url);
    //echo "test: {$subBuild->url} $body\n";
    $matches = [];
    if (preg_match_all('/\<div\s+?id="description"\>\<div\>.*?(?<wext>\(WITH EXTENSIONS\)|)\<\/div\>/s', $body, $matches))
    {
        $subBuild->withExtension = $matches['wext'][0] ? true : false;
    }
    $subBuild->failed = strpos($body, '<div class="summary_report_build">') !== false ? true : false;
    $matches = [];
    if (preg_match_all('/\<a href="(?<allure>[^"]+?)">Allure Report\<\/a\>/', $body, $matches))
    {
        $subBuild->hasAllure = true;
        $subBuild->allureUrl = $subBuild->url . $matches['allure'][0];
    }
}

function getResults($builds, $tasks)
{
    $results = [];

    foreach ($builds as $buildUrl) {
        $funcSubBuilds = getFuncSubBuilds($buildUrl);
        /** @var SubBuild $subBuild */
        foreach ($funcSubBuilds as $subBuild) {
            if (!$subBuild->hasAllure) {
                continue;
            }
            $items = $subBuild->getSuitesItems();
            foreach ($tasks as $task) {
                if (isset($items[$task])) {
                    /** @var SuiteItem $item */
                    $item = $items[$task];
                    $results[$task][$subBuild->withExtension ? 'with extensions' : 'without extensions'][$subBuild->version][] = [$item->status => $item->getUrl($subBuild->allureUrl)];
                }
            }
        }
    }

    return $results;
}

function formatResults($data)
{
    $output = '';
    foreach ($data as $task => $extensions) {
        $output .= "- *$task*\n";
        foreach ($extensions as $extension => $versions) {
            $output .= "-- *$extension*\n";
            foreach ($versions as $version => $testResults) {
                $output .= "--- *$version*\n";
                foreach ($testResults as $result) {
                    foreach ($result as $status => $url) {
                        $output .= sprintf(
                            "---- *%s*: %s\n",
                            sprintf($status == 'passed' ? "{color:#14892c}%s{color}" : "{color:#d04437}%s{color}",
                                $status),
                            $url
                        );
                    }
                }
            }
        }
    }

    return $output;
}

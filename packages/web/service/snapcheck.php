<?php
require_once('../commons/base.inc.php');
try {
    $Host = $FOGCore->getHostItem(false);
    if (!$Host->isValid()) throw new Exception('#!ih');
    // Get the Jobs if possible
    $SnapinJob = $Host->get('snapinjob');
    // Get the Snapin Tasks if possible
    $SnapinTasks = $FOGCore->getClass('SnapinTaskManager')->find(array('stateID' => array(-1,0,1),'jobID' => $SnapinJob->get('id')));
    // Cycle through all the host jobs that have awaiting snapin tasks.
    if ($SnapinJob && $SnapinJob->isValid()) {
        if ($_REQUEST['getSnapnames']) {
            foreach($SnapinTasks AS &$SnapinTask) {
                $Snapin = $SnapinTask->getSnapin();
                $SnapinNames[] = $Snapin->get('name');
            }
            unset($SnapinTask);
            $Snapins = implode(' ',(array)$SnapinNames);
        } else if ($_REQUEST['getSnapargs']) {
            foreach((array)$SnapinTasks AS &$SnapinTask) {
                $Snapin = $SnapinTask->getSnapin();
                $SnapinArgs[] = $Snapin->get('args');
            }
            unset($SnapinTask);
            $Snapins = implode(' ',(array)$SnapinArgs);
        } else {
            // Get the tasks of the job so long as they're active.
            $SnapinTasks = count($SnapinTasks);
            $Snapins = ($SnapinTasks ? 1 : 0);
        }
    }
    print $Snapins;
} catch (Exception $e) {
    print $e->getMessage();
}

<?php 
/*******************************************************************************************************
File		:	xhr.php
Author		:	Asif Basha E
Date		:	
********************************************************************************************************
Description	: 	Ajax page for backend processing....
********************************************************************************************************/

$DOCROOTBASEPATH = dirname($_SERVER['DOCUMENT_ROOT']);
include_once $DOCROOTBASEPATH."/confbm/bminit.cil14";
include_once $DOCROOTBASEPATH."/libbm/bmfuncbmsqlclass.cil14";
include_once $DOCROOTBASEPATH."/libbm/bmfuncbmgenericfunctions.cil14";
include_once $DOCROOTBASEPATH."/libbm/bmfuncbmencryptfunctions.cil14"; // This includes the contact functions
include_once $DOCROOTBASEPATH."/confbm/bmgenericarrays.cil14";
include_once $DOCROOTBASEPATH."/confbm/bmvarssearcharrincen.cil14";
header("Content-Type: application/json");

/************************************************************
  option = 1 ; Get Free , Paid Ids
  
  option = 2 ; Remove Offer for Particular Id
  
  option = 3 ; Downgrade Matri Id , make member free 
************************************************************/

$_REQUEST		= bmfuncioValidate($_REQUEST);

$option = $_REQUEST['option'];

if($option==1){

  $limit = ($_REQUEST['limit']=='' || $_REQUEST['limit']=='0' || $_REQUEST['limit'] > 75)?'75':$_REQUEST['limit'];
  $entryType = ($_REQUEST['entryType']=='' || $_REQUEST['entryType']=='0')?'F':$_REQUEST['entryType'];
  $gender = ($_REQUEST['gender']=='' || $_REQUEST['gender']=='0')?'M':$_REQUEST['gender'];
  
  getFreePaidIds();
  
}else if($option==2){
  
  $id = $_REQUEST['matriId'];
   if(!(bmfuncMatriidSanityCheck($id)) || !checkIdINdataBase($id)){
      echoData(array('success'=>'Invalid MatriId !!!'));
      exit;
   }else{
   
      removeOffer($id);
      
   }
  
  
}
else if($option==3){
  
  $id = $_REQUEST['matriId'];
   if(!(bmfuncMatriidSanityCheck($id)) || !checkIdINdataBase($id)){
      echoData(array('success'=>'Invalid MatriId !!!'));
      exit;
   }else{
      downgradeId($id); //make member free
   }
  
  
}

function getFreePaidIds(){
    
    global $DBINFO,$DBNAME,$HOST,$DOMAINTABLE,$GLOBALS,$limit,$entryType,$gender;
    
    $dbmergemaster = new bmDb();
    
	$dbmergemaster->bmDbConnById(1,'1','MM',$DBINFO['USERNAME'],$DBINFO['PASSWORD'],$DBNAME['MATRIMONYMS']);
    
    if(!$dbmergemaster->error){
    
      $selectVal = array('MatriId,EntryType,NumberOfPayments,ExpiryDate,Gender,OfferAvailable,OfferCategoryId');
      
      if($entryType=='R'){
        $selectValWhere = 'EntryType=? and Date(ExpiryDate)>Date(now()) and Gender=? and Status=0 and Authorized=1 and Validated=1 and left(MatriId,1)="M" limit 0,?';
      }else{
        $selectValWhere = 'EntryType=? and Gender=? and Status=0 and Authorized=1 and Validated=1 and left(MatriId,1)="M" limit 0,?';
      }
       $selectValWhereVal = array(array('EntryType',$entryType),array('Gender',$gender),array('limit',$limit));      
      
    
      $st1 = $dbmergemaster->bmDbSelect($DBNAME['MATRIMONYMS'],$DOMAINTABLE['TAMIL']['MATRIMONYPROFILE'],$selectVal,$selectValWhere,$selectValWhereVal);
        print_r($dbmergemaster->query);
      if($st1[1] == 0){
      
        echoData(array('success'=>'No MatriId Found'));
        $dbmergemaster->bmDbClose();
        exit;
        
      }else{
      
            $dbmergemaster->bmDbFetchArray($st1[0],$row);
            $row = bmfuncioValidate($row);
            $dbmergemaster->bmDbClose();
            echoData(array('success'=>$row));
            exit;
      }
      
    }else{
        echoData(array('success'=>'Server Error'));
        exit;
    }
    
}
function removeOffer($id){

    global $DBINFO,$DBNAME,$HOST,$DOMAINTABLE,$GLOBALS;
    
    $dbmergemaster = new bmDb();
	$dbmergemaster->bmDbConnById(1,1,'MM',$DBINFO['USERNAME'],$DBINFO['PASSWORD'],$DBNAME['MATRIMONYMS']);

    $dbmaster=new bmDb();
	$dbmaster->bmDbConnById(1,1,'M',$DBINFO['USERNAME'],$DBINFO['PASSWORD'],$DBNAME['BMOFFER']);
    
    $updateData = array('OfferCategoryId'=>'0','OfferAvailable'=>'0','DateUpdated'=>$GLOBALS["CURDATETIME"]);
    $selectValWhere = 'MatriId=?';
    $selectValWhereVal = array(array('MatriId',$id));
    
    if(!$dbmergemaster->error && !$dbmaster->error){
    
        $updateAffected	= $dbmergemaster->bmDbUpdate($DBNAME['MATRIMONYMS'], $DOMAINTABLE['TAMIL']['MATRIMONYPROFILE'], $updateData, $selectValWhere,$selectValWhereVal);
        
        if($updateAffected){
        
            $whereClause = 'MatriId=?';
            
            $whereValueArr = array(array('MatriId',$id));
            
            $deleteAffected = $dbmaster->bmDbDelete($DBNAME['BMOFFER'],$DOMAINTABLE['TAMIL']['OFFERCODEINFO'],$whereClause,$whereValueArr);
            
            if($deleteAffected){
            
                echoData(array('success'=>'Offer Removed'));
                exit;
                
            }else{
                echoData(array('success'=>'Offer May Not Exists OR Already Removed'));
                exit;
            
            }
            
        }
        
        $dbmaster->bmDbClose();
        $dbmergemaster->bmDbClose();
        exit;
        
    }else{
        echoData(array('success'=>'Server Error'));
        exit;
    }

}

function downgradeId($id){
    
    global $DBINFO,$DBNAME,$HOST,$DOMAINTABLE,$GLOBALS;
    
    $dbmergemaster = new bmDb();
	$dbmergemaster->bmDbConnById(1,1,'MM',$DBINFO['USERNAME'],$DBINFO['PASSWORD'],$DBNAME['MATRIMONYMS']);

    $updateData = array('OfferCategoryId'=>'0','OfferAvailable'=>'0','EntryType'=>'F','LastPayment'=>'0000-00-00 00:00:00','ExpiryDate'=>'0000-00-00 00:00:00','DateUpdated'=>$GLOBALS["CURDATETIME"]);
    $selectValWhere = 'MatriId=?';
    $selectValWhereVal = array(array('MatriId',$id));
    
    if(!$dbmergemaster->error){
    
        $updateAffected	= $dbmergemaster->bmDbUpdate($DBNAME['MATRIMONYMS'], $DOMAINTABLE['TAMIL']['MATRIMONYPROFILE'], $updateData, $selectValWhere,$selectValWhereVal);

        if($updateAffected){
                echoData(array('success'=>'Done, Now The Member '.$id.' Is a Free Member'));
                exit;
            }
        $dbmergemaster->bmDbClose();
        exit;
        
    }else{
        echoData(array('success'=>'Server Error'));
        exit;
    }

}

function checkIdINdataBase($id){

    global $DBINFO,$DBNAME,$HOST,$DOMAINTABLE,$GLOBALS;
    
    $dbmergemaster = new bmDb();
    
	$dbmergemaster->bmDbConnById(1,'1','MM',$DBINFO['USERNAME'],$DBINFO['PASSWORD'],$DBNAME['MATRIMONYMS']);
    
    if(!$dbmergemaster->error){
    
      $selectVal = array('MatriId');
      $selectValWhere = 'MatriId=?';
      $selectValWhereVal = array(array('MatriId',$id));
      $st1 = $dbmergemaster->bmDbSelect($DBNAME['MATRIMONYMS'],$DOMAINTABLE['TAMIL']['MATRIMONYPROFILE'],$selectVal,$selectValWhere,$selectValWhereVal);
      $dbmergemaster->bmDbClose();
      
    }
      
    return ($st1[1] > 0)?true:false;

}
  
function echoData($data,$type=1){
  
  if($type==1){
    echo json_encode($data);  
  }else{
    echo $_REQUEST["callback"].'('.json_encode($data).')';
  }
  
}
?>


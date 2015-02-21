<?php
/****************************************************************************************************
File				: recentlyupdatedapi.php
Author				: Tamil Selvi.D
Created Date		: 22 May 2014
Description 		: To get Recently updated id for app and website 
******************************************************************************************
Modified By : Tamil Selvi.D 28-May-2014 - Added updated details in RecentlyUpdated profiles
Modified    : Shinu Cherian 03 Sep 2014 New MIMA Logic implemented.
Modified    : Sathrak Paldurai K 15-12-2014 get and show to Status 3,6 Email Bounced User.
***********/
$outputArray			= array();
$outputArray['RESPONSECODE']	= 1;

if(isset($_REQUEST['APPTYPE']) && !empty($_REQUEST['APPTYPE']))
{	
	include_once "/home/apps/confbm/bminit.cil14"; 
	include_once "/home/apps/libbm/bmfuncsphinxapi.php"; //equal	
	include_once "/home/apps/libbm/bmfuncbmgenericfunctions.cil14";	
	include_once "/home/apps/libbm/bmfuncmemcache.class.php";
	#Input Validation
    $_REQUEST= bmfuncioValidate($_REQUEST);
   if(!is_matriid($_REQUEST['MID']) || !($_REQUEST['APPTYPE']) || ($_REQUEST['ENCID'] != getEncryptpass($_REQUEST['MID'], $_REQUEST['ENCID'])))
    {
        $outputArray['RESPONSECODE']	= 2; # Error
        $outputArray['ERRCODE'] = $outputArray['CONTENTCODE']		= 2; #Input Error
        appTrackingLog($_REQUEST['MID'], $_REQUEST['MID'], 6,$outputArray['ERRCODE']);
    }
}
//common files for both app and website
include_once "/home/appsbharat/libbm/bmfuncbmsqlclass_mysql.cil14"; // Include this file for using sql class
include_once "/home/appsbharat/confbm/sphinxvars.cil14";
include_once "/home/appsbharat/libbm/bmfuncsphinxclass.cil14";
include_once "/home/appsbharat/libbm/appbmfuncsphinxgenericfunction.cil14";//sphinxgenericfunction new file in app
include_once "/home/appsbharat/confbm/bmgenericarrays.cil14";
include_once "/home/appsbharat/confbm/bmvarssearcharrincen.cil14"; 
include_once "/home/appsbharat/libbm/appbmfuncrecentupdatedfunction.php";//new file in app

error_reporting(E_ALL);
if($_REQUEST['RSLOG']=="RSLOG")
{
ini_set('display_errors',1);
}
$_REQUEST = bmfuncioValidate($_REQUEST);
$memberInfo['outputtype']       = (isset($_REQUEST['OUTPUTTYPE']) && $_REQUEST['OUTPUTTYPE'] != "") ? $_REQUEST['OUTPUTTYPE'] : 2;  # OUTPUTTYPE : 1 - XML, 2 - JSON.

$loginid         = (isset($_REQUEST['MID']) && $_REQUEST['MID'] != "") ? $_REQUEST['MID'] : $memberid; # Login Member Id

$gender	        = (isset($_REQUEST['GENDER']) && $_REQUEST['GENDER'] != "") ? $_REQUEST['GENDER'] : $gender;

$PartnerPrefSet	= (isset($_REQUEST['PARTNERPREFSET']) && $_REQUEST['PARTNERPREFSET'] != "") ? $_REQUEST['PARTNERPREFSET'] : $PartnerPrefSet;

$gothraid	        = (isset($_REQUEST['GOTHRAID']) && $_REQUEST['GOTHRAID'] != "") ? $_REQUEST['GOTHRAID'] : $gothraid;
$AppType 	= trim($_REQUEST['APPTYPE']);
$OutputType	= trim($_REQUEST['OUTPUTTYPE']);

$lastlogin= date('Y-m-d');

$sphinxmatriid=bmfuncConvertToSphinxMatriIdFormat($loginid);
$PartnerPrefSet=1;

if($PartnerPrefSet==1)
{
	$partnerprefvalue    = bmfuncgetSearchFieldsfrmMemCache("PP-".$loginid);//bmfuncbmgenericfunctions
}
else
{
    $partnerprefvalue = bmfuncgetSearchFieldsfrmMemCache("SYSTEMPP-".$loginid);//bmfuncbmgenericfunctions
}
if(empty($partnerprefvalue) && isset($_REQUEST['APPTYPE']) && !empty($_REQUEST['APPTYPE']) )
{	
	if($PartnerPrefSet!=1){ 
		$partnerprefvalue	= appGetPPvalue($loginid,"SYSTEMPP-".$loginid);
	}
	else{ #For Match Profiles and Preferred Profiles
		$partnerprefvalue	= appGetPPvalue($loginid,"PP-".$loginid);
	} 
}
if(!empty($partnerprefvalue))
{
	$memPp	=	bmfuncgetPartnerPrefInfo($partnerprefvalue); //bmfuncrecentupdatedfunction 
}
/* Brahmin - All Module start */
if($memPp['ppmatchreligion'][0] == 1) // check for hindu religion
{
	if(is_array($memPp['ppmatchcaste']) && in_array(1000,$memPp['ppmatchcaste']))
	{	
		$memPpmothrtongue=implode("~",$memPp['ppmothertongue']);
		$castehash = bmfuncSearchCasteMapping($memPpmothrtongue);//bmfuncsphinxgenericfunction
		if($castehash!='')
		{
			$memPpcaste = implode("~",$memPp['ppmatchcaste']);
			$addcastehash = $memPpcaste.'~'.$castehash;
			$memPp['ppmatchcaste'] = array_unique(explode("~",$addcastehash));
		}
	}
}
$memPp	=	bmfuncioValidate($memPp);
if(isset($_REQUEST['APPTYPE']) && !empty($_REQUEST['APPTYPE']))
{
	$sphinxoutput	= getRecentlyUpdatedProfiles($loginid ,'myhome');
}
function getRecentlyUpdatedProfiles($matriid,$pagefrom='',$totalfoundval="")
{
	global $SPHINXIPCONF,$SPHINXDBCONIPPORT,$lastlogin,$memPp,$OutputType;
	$memberPartnerInfo=$memPp;
	
	$objSphinxRecentlyUpdateDb = new sphinxdb(); // creating sphinx obj - Sphinx2 Server
	$objSphinxMemberToolsDb = new sphinxdb(); // creating sphinx obj for membertoolsindex and bookmarkedindex(spx4,spx5,spx6,spx7)
	$arryDomainInfo= getDomainInfo(1,$matriid);
	$domainid = $arryDomainInfo['domainid'];
	//This from 'SPX10_RC2'
	$sphinxMemberToolsDomainInfo	= $objSphinxMemberToolsDb->sphinxMatchSummarygetDomainInfo($domainid); 
	
	if($sphinxMemberToolsDomainInfo["ip"] !="")
	{

		//Sphinx connection for accessing memberrecentupdates index
		$sphinxRecentlyUpdateConn		    = $objSphinxRecentlyUpdateDb->SphinxConnect($sphinxMemberToolsDomainInfo["ip"],$sphinxMemberToolsDomainInfo["port"], 'SPH_MATCH_FULLSCAN','3000');
		$sphinxRecentlyUpdateErr			= $objSphinxRecentlyUpdateDb->GetLastError();
		//This for'SPX10_RC2' [bookmarked,membertoolslog]
		$sphinxMemberToolsConn		    = $objSphinxMemberToolsDb->SphinxConnect($sphinxMemberToolsDomainInfo["ip"],$sphinxMemberToolsDomainInfo["port"], 'SPH_MATCH_FULLSCAN','3000');
		$sphinxMemberToolseErr			= $objSphinxMemberToolsDb->GetLastError();
		
		if(trim($sphinxRecentlyUpdateErr) <> "" || trim($sphinxMemberToolseErr) <> "")
		{
			if(trim($sphinxRecentlyUpdateErr) <> ""){
				$ip=$sphinxMemberToolsDomainInfo["ip"];
				$port=$sphinxMemberToolsDomainInfo["port"];
				$sphinxErr=$sphinxRecentlyUpdateErr;
			}elseif(trim($sphinxMemberToolseErr) <> ""){
				$ip=$sphinxMemberToolsDomainInfo["ip"];
				$port=$sphinxMemberToolsDomainInfo["port"];
				$sphinxErr=$sphinxMemberToolseErr;
			}
			
			$erroMsg = "\n MatriId : ".$matriid."IP : ".$ip." PORT : ".$port." SearchdErr : ".$sphinxErr." Time ".date("H:i:s:u")." Connection error on Sphinx in MemberRecentUpdate"; 
			$file_name = "/var/log/bmlog/sphinxlog/".date('d-m-Y')."_".$_SERVER['SERVER_ADDR']."_memberrecentupdates.txt";
			createErrorLog($file_name);
			if($_GET['RSLOG'] =='RSLOG')
			{
				echo $erroMsg;
			}
			if($AppType)//For APP
			{
				$outputArray['ERRCODE'] = 1; 
				$outputArray['RESPONSECODE'] = 2; 
				appTrackingLog($matriid,'',30,0);
				appOutput($outputArray, $OutputType);
			}
			exit;
		
		}
		else
		{
			$objSphinxRecentlyUpdateConn1=$sphinxRecentlyUpdateConn;
			$objSphinxMemberToolsConn1=$sphinxMemberToolsConn;	
			$beforeOneWeekDate=mktime(0, 0, 0, date("m"),date("d")-7,date("Y"));
			$yesterdayDate=mktime(0, 0, 0, date("m"),date("d")-1,date("Y"));
			//shortlisted profiles (SHORTLISTINDEX)		
			$shorListBmids=getProfilenotesResultIds(1,$objSphinxMemberToolsConn1,$matriid);
			//viewed list from memcache		
			$viewedList=getViewedIdsFromMemcahe($matriid); 	
			//DECLINELISTINDEX			
			$declinedIDs=getMemberDeclineListRecentlyUpdated($objSphinxMemberToolsConn1,$matriid);
			//ignore profiles	MEMBERLISTINDEX
			$ignoredListBmids=getProfilenotesResultIds(2,$objSphinxMemberToolsConn1,$matriid); 
			
			if(strtotime($lastlogin)>=$beforeOneWeekDate)
			{	//Already viewed profiles (MEMBERTOOLSRECENTUPDATEINDEX)
				$memberToolsBmids=getMembertoolsLogInfo($objSphinxMemberToolsConn1,1,$matriid); 
			}
			else
			{
				$memberToolsBmids=array();
			}
			if($_REQUEST['rslog']=='rslog')
			{
				echo "viewedList:<pre>";print_r($viewedList);echo "</pre>";
				echo "ignoredList:<pre>";print_r($ignoredListBmids);echo "</pre>";
				echo "memberTools:<pre>";print_r($memberToolsBmids);echo "</pre>";
				echo "declinedIDs:<pre>";print_r($declinedIDs);echo "</pre>";
			}
			$tobeAvoidIds=array_unique(array_merge((array)$viewedList,(array)$ignoredListBmids,(array)$memberToolsBmids,(array)$declinedIDs));
			$tobeAvoidIdsCount = count($tobeAvoidIds);
			if($tobeAvoidIdsCount > 14000)
			{
				ini_set("memory_limit","64M");
			}			
			$recentlyupdatedids = getRecentlyUpdatedIds($matriid,$memberPartnerInfo,$shorListBmids,$tobeAvoidIds,$pagefrom,$objSphinxRecentlyUpdateConn1);
			
			foreach($recentlyupdatedids as $key=>$value)
			{
				if($key!="total_found")
				{
					$addedFields=bmfuncgetAddedFields($recentlyupdatedids[$key]);
					$updatedFields=bmfuncgetUpdatedFields($recentlyupdatedids[$key]);	
					$formatedAddedFields=$formatedUpdatedFields='';
					
					if($updatedFields && $addedFields){
					
						$formatedAddedFields=bmfuncgetFormatedContent($addedFields);
						$formatedUpdatedFields=" and also updated ".bmfuncgetFormatedContent($updatedFields);
						$recentlyupdatedids[$key]['UpdatedandAdded']=$formatedAddedFields.$formatedUpdatedFields;
					}else if($addedFields){
						$formatedAddedFields=bmfuncgetFormatedContent($addedFields);
						$recentlyupdatedids[$key]['UpdatedandAdded']=$formatedAddedFields;
					}
					else{
						$formatedUpdatedFields=bmfuncgetFormatedContent($updatedFields);
						$recentlyupdatedids[$key]['UpdatedandAdded']=$formatedUpdatedFields;
					}
				}
			}
				
			

			if(isset($_REQUEST['APPTYPE']) && !empty($_REQUEST['APPTYPE']))
			{
				$appOutputArray = array(
					 'RESPONSECODE' => '1',
					 'ERRCODE'      => '0',
					 'TOTALRESULT'  => $recentlyupdatedids
				  );
				  appOutput($appOutputArray,$OutputType);
				
			}
			else
			{	
				return $recentlyupdatedids;
								
			}
			
		}
	}
}

function getProfilenotesResultIds($accessType,$objSphinxMemberToolsConn1,$matriid)
{			
	$returnIds=array();
	$objSphinxMemberToolsConn1->ResetFilters();
	$objSphinxMemberToolsConn1->SetSelect("*");
	if($accessType==1){//for getting shortlisted ids
		$results=bmfuncGetSphinxProfilenotesResultSet($matriid, 4,$objSphinxMemberToolsConn1);
		$returnIds=$results['shortlisted'];
	}elseif($accessType==2){ //for getting ignored ids
		$results=bmfuncGetSphinxProfilenotesResultSet($matriid, 3,$objSphinxMemberToolsConn1);
		$returnIds=$results['ignorededlist'];
	}elseif($accessType==3){ //for getting viewed ids
		$results=bmfuncGetSphinxProfilenotesResultSet($matriid, 1,$objSphinxMemberToolsConn1);
		$returnIds=$results['viewedlist'];
	}
	return $returnIds;
}

function getViewedIdsFromMemcahe($matriid)
{
	$memViewed=array();
	$mkey = $matriid."-VIEWED";
	$memcacheViewed= Cache::get($mkey);
	if($memcacheViewed != "") {
		$memcacheViewedArr = explode(",",$memcacheViewed);   
		foreach ($memcacheViewedArr as $memValue) {   
			$memViewed[] = bmfuncConvertToSphinxMatriIdFormat($memValue);   }
	}
	return $memViewed;
}
#Function to return the Decline List from Sphinx
function getMemberDeclineListRecentlyUpdated($objSphinxMemberToolsConn1,$matriid)
{
	 global $languageArray,$SPHINXINDEXNAME,$APPSPHINXINDEXNAME;
	 $sphinx_matriid = bmfuncConvertToSphinxMatriIdFormat($matriid);
	 $arryDomainInfo= getDomainInfo(1,$matriid);
	 $domainname     = strtolower($arryDomainInfo['domainnameshort']); 
	 $objSphinxMemberToolsConn1->ResetFilters();
	 $query 	 = '';
	 $declineresult = array();
	 $objSphinxMemberToolsConn1->SetFilter("ProfileIndex",array($sphinx_matriid));
	 $declineresult  =  $objSphinxMemberToolsConn1->Query($query,$SPHINXINDEXNAME[strtoupper($domainname)]['DECLINELISTINDEX']);
	 if($_REQUEST['rslog']=='rslog')
	 {
		echo "DeclineListIndexConn:<pre>";print_r($objSphinxMemberToolsConn1);echo "</pre>";
		echo "declineresult:<pre>";print_r($declineresult);echo "</pre>";
	 }
	 $declineresult  =$declineresult['matches'][0]['attrs']['contactedlist'];
	
	 return $declineresult;
}

function getMembertoolsLogInfo($objSphinxMemberToolsConn1,$accessType,$matriid)
{
	global $SPHINXINDEXNAME,$APPSPHINXINDEXNAME;
	$arryDomainInfo= getDomainInfo(1,$matriid);

	$domainname = strtolower($arryDomainInfo['domainnameshort']); 
	$membertoolsindex=isset($SPHINXINDEXNAME[strtoupper($domainname)]['MEMBERTOOLSRECENTUPDATEINDEX'])?$SPHINXINDEXNAME[strtoupper($domainname)]['MEMBERTOOLSRECENTUPDATEINDEX']:'';
	$objSphinxMemberToolsConn1->ResetFilters();
	$objSphinxMemberToolsConn1->SetArrayResult(true);
	//For getting matriid
		$objSphinxMemberToolsConn1->SetFilter("ProfileIndex", array(bmfuncConvertToSphinxMatriIdFormat($matriid)));
		$objSphinxMemberToolsConn1->SetSelect('viewedidindex,IF(DateViewed>DateUpdated,1,0) as DateFilter');
		$objSphinxMemberToolsConn1->SetFilter("DateFilter", Array(1));
		$setSelect="viewedidindex";
		$resMatches=getMatches(getRecords($membertoolsindex,$objSphinxMemberToolsConn1,$matriid,1),$setSelect);
		return $resMatches;
}
function getRecords($indexname,$sphxCon,$matriid,$restype)
{
	global $loginid,$DBINFO,$DBNAME,$gender;
	$varqueryname=" - Recent updates - Myhome";
	$totalRecordFound=0;
	if($_REQUEST['rslog']=='rslog')
	{
		echo " indexname:".$indexname;
	}
	if($indexname)
	{
			$query = "";
			$sphinxRes = $sphxCon->Query($query,$indexname,date("d-m-y H:i:s:").substr(microtime(), 2, 4)." - ".$matriid.$varqueryname);
			if($_REQUEST['RSLOG'] == 'RSLOG'){
				echo '<pre>';print_r($sphxCon);echo '</pre>';
				echo '<pre>';print_r($sphinxRes);echo '</pre>';
			}
			$totalRecordsFound=$sphinxRes['total_found'];
			$parseresult[0] = $sphinxRes;
			if($totalRecordsFound > 0)
			{
				$resultArray = bmfuncgetParseValuesAsArray($parseresult, array("profileindex","age","incms","height","religion","caste","castenobar","countryselected","residingstate","residingdistrict","photoavailable","maritalstatus","mothertongue","residingcityid","photoprotected","entrytype","name","residingarea","casteothers","thumbimg","thumbimgs","time_created","phonestatus","horoscopestatus","profiledescription","photostatus","language","subcasteid","gothraid","educationselected","occupationcategory","occupationselected","star","phoneverified","phoneprotected","horoscopeavailable","videoavailable","specialpriv","profileverified","voiceavailable","specialcase","dosham","eatinghabits","citizenship","residentstatus","referenceavailable","healthprofileavailable","educationid","residingcityid","powerpackstatus","horoscopeprotected","smokinghabits","drinkinghabits","annualincomeininr","have_children","subcaste","gothra","education","occupation","bywhom","powerpackopted","validated","mothertongueothers","status","last_login","city_selected","state_selected","gender"));	

			$phoavalids=array();
			foreach($resultArray as $rukey=>$ruval){
				if($ruval['PhotoAvailable']==1 && $ruval['PhotoProtected']!='Y'){
					$phoavalids[]=$rukey;
				}
			}
			if(!empty($phoavalids)){
				$dbcon = new bmDb();
				$dbcon->bmDbConnById(2,$loginid,'S',$DBINFO['USERNAME'],$DBINFO['PASSWORD'],$DBNAME['MATRIMONYMS']);
				if(!$dbcon->error){
					$photo_array=recentupdategetPhotoInfo($phoavalids,$dbcon);
					$dbcon->bmDbclose();
					foreach($photo_array as $pkey=>$pval){
						$resultArray[$pkey]['ThumbImg']=$photo_array[$pkey]['ThumbImg1'];
						$resultArray[$pkey]['ThumbImgs']=$photo_array[$pkey]['ThumbImgs1'];
					}
				}
			}	
			$OppGender = $gender=='F'?'M':'F';

			$photoInfoDetails = getAllPhotoURL($OppGender,$resultArray);
			foreach($resultArray as $key => $value)
			{
				$resultArray[$key]['ThumbImg']=$photoInfoDetails[$key]['ThumbImg'];
				$resultArray[$key]['ThumbImgs']=$photoInfoDetails[$key]['ThumbImgs'];
			}	
		}
		$resultArray['total_found']=$sphinxRes['total_found'];
		if($restype==1)
		{
			if($_REQUEST['rslog']=='rslog')
			{	
				echo " SphinxMemberToolsConn  <pre>";print_r($sphxCon);echo "</pre>";
				echo " membertoolsindexresult  <pre>";print_r($sphinxRes);echo "</pre>";
			}
			return $sphinxRes ;	
		}
		else if($restype==2)
		{
			if($_REQUEST['rslog']=='rslog')
			{	echo " SphinxRecentupdateConn  <pre>";print_r($sphxCon);echo "</pre>";
				echo " Recentlyupdatedresult  <pre>";print_r($resultArray);echo "</pre>";
			}
			return $resultArray ;
		}
			
	}else{
		 return false;
	}
}
function getMatches($resultArr,$field="")
{
		$fullarray = array();
		if($resultArr["total_found"] > 0)
		{
			foreach($resultArr["matches"] as $key => $value)
			{
				if($field ){
					$fullarray[] = $resultArr["matches"][$key]["attrs"][$field];
				}else{
						$fullarray[] = $resultArr["matches"][$key]["attrs"];
					}
			}
			
			return $fullarray;
		}
}
function getRecentlyUpdatedIds($matriid,$memberPartnerInfo,$bookmarkedIds,$tobeAvoidIds,$pagefrom,$objSphinxRecentlyUpdateConn1)
{
	global $gender,$PartnerPrefSet,$lastlogin,$mobileverifysuperflag,$blockuseractionflag,$COOKIEINFO,$SEARCHLOGICFLAG;
	$beforeOneWeek=mktime(0, 0, 0, date("m"),date("d")-7,date("Y"));
	$yesterday=mktime(0, 0, 0, date("m"),date("d")-1,date("Y"));
	$recentlyupdatedindex="recentlyupdatedindex";
	$resultArr =getPartnerePreferenceQry($memberPartnerInfo);
	$res = $resultArr[0];
	$conditionQry = $resultArr[1];
	$objSphinxRecentlyUpdateConn1->ResetFilters();
	$objSphinxRecentlyUpdateConn1->SetArrayResult(true);
	$setSelectFields='*';

	if($PartnerPrefSet == 1)
	{
		#******************************************#
		#*Setselect for preferred profiles  Starts*#
		#******************************************#
		$ppfilter = '';		
		if(in_array(0, $memberPartnerInfo['ppmatchcaste']) == FALSE)
		{
			#Select Subcaste
			$subcaste			= $memberPartnerInfo['ppmatchsubcaste'];			
			$implodesubcaste	= implode(',', $subcaste);
			$ppfilter			= " IN(SubCasteId, $implodesubcaste) ";
		}
		//Select Have children
		#If member preference has only unamarried, Have children will not be considered for ideal match
		#values of $matchcond['MaritalStatus']	
		#0 - Doesn't matter
		#2 - Yes. living together
		#3 - Yes. not living together
		#1 - No
		$ppmaritalstatus	= $memberPartnerInfo['ppmaritalstatus'];
		$pphavingchildren	= $memberPartnerInfo['pphavingchildren'];
		if(count($ppmaritalstatus) != 1 && $ppmaritalstatus[0] != 1)
		{
			if($pphavingchildren)
			{
				#If member select NO(1), change the value to 0; 
				#If PP is Yes, Living together , then value is [2]; In matrimonyprofile Yes, Living together , has value [1] 
				#If PP is Yes, Not Living together , then value is [3]; In matrimonyprofile Yes, Living together , has value [2] 
				if($pphavingchildren >= 1)	
					$pphavingchildren = $pphavingchildren-1; 
				$pphavingchildren = $pphavingchildren.',100'; 
				if($ppfilter!=''){$ppfilter	.=" AND ";}
					$ppfilter.=" IN(have_children, $pphavingchildren) ";
			}
		}		

		#Select Star	
		$ppstarid = $memberPartnerInfo['ppstarid'];
		
		if(in_array(0,$ppstarid) == FALSE && count($ppstarid) >= 1)
		{
			$implodestarright		= implode(',', $ppstarid);
			if($ppfilter!=''){$ppfilter.=" AND ";}
			$ppfilter.=" IN(Star, $implodestarright)";
		}

		#Select Smoking habits
		$ppsmokinghabitspref = $memberPartnerInfo['ppsmokinghabitspref'];
		if(in_array(0,$ppsmokinghabitspref) == FALSE && count($ppsmokinghabitspref) >= 1)
		{		
			$implodesmoking			= implode(',', $ppsmokinghabitspref);
			if($ppfilter!=''){$ppfilter.=" AND ";}
			$ppfilter .=" IN(SmokingHabits, $implodesmoking)";
		}

		#Select Drinking habits
		$ppdrinkinghabitspref = $memberPartnerInfo['ppdrinkinghabitspref'];
		if(in_array(0,$ppdrinkinghabitspref) == FALSE && count($ppdrinkinghabitspref) >= 1)
		{
			$implodedrinking			= implode(',', $ppdrinkinghabitspref);
			if($ppfilter!=''){$ppfilter.=" AND ";}
			$ppfilter .=" IN(DrinkingHabits, $implodedrinking)";
		}

		#Select Occupation Selected
		$ppmatchoccupationselected = $memberPartnerInfo['ppmatchoccupationselected'];
		if(in_array(0,$ppmatchoccupationselected) == FALSE && count($ppmatchoccupationselected) >= 1)
		{
			$implodeoccupation			= implode(',', $ppmatchoccupationselected);
			if($ppfilter!=''){$ppfilter.=" AND ";}
			$ppfilter				   .= "  IN(OccupationSelected, $implodeoccupation)";
		}

		#Set Select Annual Income Filter Starts 
		$ppstincome						= $memberPartnerInfo['ppstincome'];
		$ppendincome					= $memberPartnerInfo['ppendincome'];

		if(((in_array(98, $memberPartnerInfo["ppcountry"]) || in_array(0, $memberPartnerInfo["ppcountry"]) || empty($memberPartnerInfo["ppcountry"]))&& $ppstincome > 0) || ( empty($memberPartnerInfo["ppcountry"])) )
		{ 
			#If member selected India and annual income is not any
			$income_output		= set_income($ppstincome, $ppendincome, 1);#For INR Calculation
			if($income_output)
			{ 
				if($ppfilter!=''){$ppfilter.=" AND ";}
				$ppfilter .=" AnnualIncomeinINR>=".$income_output[0]." AND AnnualIncomeinINR<=".$income_output[1];
			}
		}
		else
		{ 
			#If member have not selected India in country
			$income_output		=set_income($ppstincome, $ppendincome, 2);#For USD Calculation
			if($income_output)
			{
				if($ppfilter!='')
				{
					$ppfilter.=" AND ";
				}
				$ppfilter .=" AnnualIncomeinINR>=".$income_output[0]." AND AnnualIncomeinINR<=".$income_output[1];
			}				
		}	
		#Set Select Annual Income Filter Ends
		if(isset($res["countryStateCityQuery"]) && $res["countryStateCityQuery"] != '')
		{
			if($ppfilter!='')
			{
				$ppfilter.=" AND ";
			}
			$ppfilter .= " (".$res["countryStateCityQuery"].")";
		}
	}
	if($conditionQry)
	{
		$conditionQry.="AND (photostatus<>0 OR phonestatus<>0 OR horoscopestatus<>0 OR profiledescriptionstatus<>0 OR locationstatus<>0 OR professionstatus<>0 OR ppstatus<>0)";
	}
	else
	{
		$conditionQry.="(photostatus<>0 OR phonestatus<>0 OR horoscopestatus<>0 OR profiledescriptionstatus<>0 OR locationstatus<>0 OR professionstatus<>0 OR ppstatus<>0)";
	} 


	$scoreQuery="";
	if($SEARCHLOGICFLAG==1 && $_REQUEST['APPTYPE']==""){
		
		$stheight = floor($memberPartnerInfo['ppstheight']);
		$endheight = ceil($memberPartnerInfo['ppendheight']);
		
		$userpref=array();
		$memberPref=array();
		$userpref = array("StAge"=>$memberPartnerInfo['ppstage'],"EndAge"=>$memberPartnerInfo['ppendage'],"StHeight"=>$stheight,"EndHeight"=>$endheight,"BodyType"=>array(),"Complexion"=>array(),"EatingHabits"=>$memberPartnerInfo['ppeatinghabitspref'],"SmokingHabits"=>$memberPartnerInfo['ppsmokinghabitspref'],"DrinkingHabits"=>$memberPartnerInfo['ppdrinkinghabitspref'],"OccupationCategory"=>array(),"StAnnualIncome"=>$memberPartnerInfo['ppstincome'],"EndAnnualIncome"=>$memberPartnerInfo['ppendincome'],"OccupationSelected"=>$memberPartnerInfo['ppmatchoccupationselected'],"Citizenship"=>$memberPartnerInfo['ppcitizenship'],"SubCasteId"=>$memberPartnerInfo['ppmatchsubcaste'],"EducationId"=>$memberPartnerInfo['ppmatcheducation'],"Religion"=>$memberPartnerInfo['ppmatchreligion'],"Caste"=>$memberPartnerInfo['ppmatchcaste'],"MotherTongue"=>$memberPartnerInfo['ppmothertongue'],"MaritalStatus"=>$memberPartnerInfo['ppmaritalstatus'],"SpecialCase"=>$memberPartnerInfo['ppphysicalstatus']);
		
		$memberPref = array("Caste"=>$COOKIEINFO['LOGININFO']['CASTE'],"MotherTongue"=>$COOKIEINFO['LOGININFO']['MOTHERTONGUE'],"SubCasteId"=>array(),"Dosham"=>array(),"MemberId"=>$COOKIEINFO['LOGININFO']['MEMBERID']);
		
		$scoreQuery = frameEPPSetselectQuery($userpref,$memberPref,"","RU");
		
		if($scoreQuery != "")
			$scoreQuery = ",".str_replace('last_login_orderby','last_login',$scoreQuery);
		
	}
	
	$conBookMarkedIds=implode(',',(array)$bookmarkedIds);
	if(trim($conditionQry) != '' && strlen($conBookMarkedIds) > 4) //bookmarked and partnerepref
	{
		if($ppfilter != '' && $PartnerPrefSet == 1)
		{ 
			$setSelectFields.=$scoreQuery.",IF(IN(profileindex,$conBookMarkedIds),4,IF((".$conditionQry."),1,0)) + IF(($ppfilter), 2,0) AS ppfilter";
			$ppfiltersetselect="IF(IN(profileindex,$conBookMarkedIds),4,IF((".$conditionQry."#ADDQRY#),1,0)) + IF(($ppfilter), 2,0)"; # Created a new setselect it will be added with the previous setselect value
			$objSphinxRecentlyUpdateConn1->SetSelect($setSelectFields);
			$objSphinxRecentlyUpdateConn1->SetFilter("ppfilter",array(1,3,4,6));	
			//3,6 - Preferred Profile Match; 
			//1,4 - Actual Match
		}
		else
		{
			$setSelectFields.=$scoreQuery.",IF(IN(profileindex,$conBookMarkedIds),4,IF((".$conditionQry."),1,0)) as ppfilter";
			$ppfiltersetselect="IF(IN(profileindex,$conBookMarkedIds),4,IF((".$conditionQry."#ADDQRY#),1,0))";# Created a new setselect it will be added with the previous setselect value
			$objSphinxRecentlyUpdateConn1->SetSelect($setSelectFields);
			$objSphinxRecentlyUpdateConn1->SetFilter("ppfilter",array(1,4));
			//1,4 - Actual Match otherwise Not a match
		}
	}
	else if (trim($conditionQry) != '' && strlen($conBookMarkedIds) <= 4) // partnerepref
	{	
		if($ppfilter != '' && $PartnerPrefSet == 1)
		{
			$setSelectFields.=$scoreQuery.",IF((".$conditionQry."),1,0) + IF(($ppfilter), 2,0) AS ppfilter";
			$ppfiltersetselect="IF((".$conditionQry."#ADDQRY#),1,0) + IF(($ppfilter), 2,0)";# Created a new setselect it will be added with the previous setselect value

			$objSphinxRecentlyUpdateConn1->SetSelect($setSelectFields);
			$objSphinxRecentlyUpdateConn1->SetFilter("ppfilter",array(1,3));	
			//3 - Preferred Profile Match; 
			//1 - Actual Match
		}
		else
		{
			$setSelectFields.=$scoreQuery.",IF((".$conditionQry."),1,0) as ppfilter";
			$ppfiltersetselect="IF((".$conditionQry."#ADDQRY#),1,0)"; # Created a new setselect it will be added with the previous setselect value
			$objSphinxRecentlyUpdateConn1->SetSelect($setSelectFields);
			$objSphinxRecentlyUpdateConn1->SetFilter("ppfilter",array(1));
			//1 - Actual Match otherwise Not a match
		}
	}
	else if(trim($conditionQry) == '' && strlen($conBookMarkedIds) > 4) //bookmarked
	{
		$ppfiltersetselect="IF((#ADDQRY#),1,0)"; # Created a new setselect it will be added with the previous setselect value
		//bookmarkids avail; condqry not avail - Senario is handled to avoid issues or invalid data
		if($ppfilter != '' && $PartnerPrefSet == 1)
		{
			
			$setSelectFields.=$scoreQuery.",IF(IN(profileindex,$conBookMarkedIds),1,0) + IF(($ppfilter), 2,0) AS ppfilter";
			$objSphinxRecentlyUpdateConn1->SetSelect($setSelectFields);
			$objSphinxRecentlyUpdateConn1->SetFilter("ppfilter",array(1,3));	
			//3 - Preferred Profile Match; 
			//1 - Actual Match
		}
		else
		{
			$setSelectFields.=$scoreQuery.",IF(IN(profileindex,$conBookMarkedIds),1,0) as ppfilter";
			$objSphinxRecentlyUpdateConn1->SetSelect($setSelectFields);
			$objSphinxRecentlyUpdateConn1->SetFilter("ppfilter",array(1));
			//1 - Actual Match otherwise Not a match
		}
	}

	#################################################3		
	if(count($tobeAvoidIds) > 0)
	{
		$objSphinxRecentlyUpdateConn1->SetFilter("ProfileIndex",$tobeAvoidIds,true);//Not in condition
	}

	#Getting last one week data
	$objSphinxRecentlyUpdateConn1->SetFilterRange("date_updated",$beforeOneWeek,$yesterday); //remove comment
	$objSphinxRecentlyUpdateConn1->SetFilter("validated",array(1));   
	$objSphinxRecentlyUpdateConn1->SetFilter("authorized",array(1));  
	$objSphinxRecentlyUpdateConn1->SetFilter("status",array(0,3,6));      
	$objSphinxRecentlyUpdateConn1->SetFilter("deleted",array(0));
	if($mobileverifysuperflag == 1 && $blockuseractionflag == 1) 
	   $objSphinxRecentlyUpdateConn1->SetFilter("PhoneVerified",array(1,3));	   
	$starLimit=0;
	$endLimit=10;
	$objSphinxRecentlyUpdateConn1->SetLimits($starLimit,$endLimit);
	
	if($SEARCHLOGICFLAG==1 && $scoreQuery != ""){
		$objSphinxRecentlyUpdateConn1->SetSortMode(SPH_SORT_EXTENDED, "scores ASC");
	}else{
		$objSphinxRecentlyUpdateConn1->SetSortMode(SPH_SORT_ATTR_DESC, "ppfilter");
	}
	
	if($pagefrom=='myhome')
	{
		$UpdatedProfiles = array();
		$UpdatedProfiles = getRecords($recentlyupdatedindex,$objSphinxRecentlyUpdateConn1,$matriid,2);
		return $UpdatedProfiles;
	}	
}

function getPartnerePreferenceQry($memberPartnerInfo)
{
	global $gender,$PartnerPrefSet,$lastlogin,$gothraid;
		$conditionQry="";
		if(!empty($memberPartnerInfo)){
			# MaritalStatus
			if(in_array('0',$memberPartnerInfo['ppmaritalstatus'])==FALSE && !empty($memberPartnerInfo['ppmaritalstatus'])){
			   $conditionQry = getINQuery($memberPartnerInfo['ppmaritalstatus'],"MaritalStatus");
			}
		 
			# Age
			if($memberPartnerInfo['ppstage'] > 0 && $memberPartnerInfo['ppendage'] > 0){
				$conditionQry .= " AND (Age>=".$memberPartnerInfo['ppstage']." AND Age<=".$memberPartnerInfo['ppendage'].")";
			}  

			# Height
			if($memberPartnerInfo['ppstheight'] > 0 && $memberPartnerInfo['ppendheight'] > 0){
				$conditionQry .= " AND (Height>=".$memberPartnerInfo['ppstheight']." AND Height<=".$memberPartnerInfo['ppendheight'].")";
			}

		   # PhysicalStatus
			if($memberPartnerInfo['ppphysicalstatus'] < 2 && trim($memberPartnerInfo['ppphysicalstatus']) !="" ){
				// PP     0 - Normal; 1- Physically Challenged; 2 - doesn't matter.  
				// Member 0 - Normal; 1- Physically Challanged
				$conditionQry .= " AND (SpecialCase=".$memberPartnerInfo['ppphysicalstatus'].")";
			}

			# Mothertonque
			if(in_array(0, $memberPartnerInfo['ppmothertongue']) == FALSE && !empty($memberPartnerInfo['ppmothertongue'])){
				$conditionQry .= " AND (".getINQuery($memberPartnerInfo['ppmothertongue'],"MotherTongue").")";
			}
  
			# Caste
			if(in_array(0, $memberPartnerInfo['ppmatchcaste']) == FALSE && !empty($memberPartnerInfo['ppmatchcaste']))	
			{			
				if(count($memberPartnerInfo['ppmatchcaste']) == 1 && $memberPartnerInfo['ppmatchcaste'][0] == 998)
				{		
					$conditionQry .= " AND (CasteNoBar=1)";
				}
				else if(count($memberPartnerInfo['ppmatchcaste']) > 1 && in_array(998, $memberPartnerInfo['ppmatchcaste']))
				{
					$castenobarKey = array_keys($memberPartnerInfo['ppmatchcaste'], 998);
					unset($memberPartnerInfo['ppmatchcaste'][$castenobarKey[0]]);
					$implodeCaste = implode(",", $memberPartnerInfo['ppmatchcaste']);
					$conditionQry .= " AND (IN(Caste,$implodeCaste) OR IN(CasteNoBar,1))";				
				}
				else if(count($memberPartnerInfo['ppmatchcaste']) > 0 && $memberPartnerInfo['ppmatchcaste'][0]!="")
				{
				 	$conditionQry .= " AND (".getINQuery($memberPartnerInfo['ppmatchcaste'],"Caste").")";
				}
			}
		   
			# Religion
			if(in_array(0,$memberPartnerInfo['ppmatchreligion'])==FALSE && !empty($memberPartnerInfo['ppmatchreligion'])) { 
				if(in_array(25, $memberPartnerInfo['ppmatchreligion']))	// Muslim
					$religion = array_merge($memberPartnerInfo['ppmatchreligion'],array(10,11));
				else if(in_array(26, $memberPartnerInfo['ppmatchreligion']))	//Christian
					$religion = array_merge($memberPartnerInfo['ppmatchreligion'],array(12,13,14));
				else if(in_array(27, $memberPartnerInfo['ppmatchreligion']))	//Jain
					$religion = array_merge($memberPartnerInfo['ppmatchreligion'],array(15,16));
				else
					$religion = $memberPartnerInfo['ppmatchreligion'];
				$conditionQry .= " AND (".getINQuery($religion,"Religion").")";
			} 
		 
			# Gothra
			if(in_array(0, $memberPartnerInfo['ppgothraid']) == FALSE && !empty($memberPartnerInfo['ppgothraid']))
			{		
				if(in_array(998, $memberPartnerInfo['ppgothraid']) && $memberInfo['MemberGothraId'] > 0){
					$conditionQry .= " AND (GothraId<>".$memberInfo['MemberGothraId'].")";//All Except My gothra
				}else{
					$allExceptMyGothraKey = array_keys($memberPartnerInfo['ppgothraid'], 998);
					unset($memberPartnerInfo['ppgothraid'][$allExceptMyGothraKey[0]]);
					if(count($memberPartnerInfo['ppgothraid']) > 0)
						$conditionQry .= " AND (".getINQuery($memberPartnerInfo['ppgothraid'],"GothraId").")";
					
				}
			}
			

			/*
			Matchwatch Pref - Dosham/Manglik
			1 - Yes; 2 - No; 3 - Not given; 0 - Not Selected [Any]
			If memberpref is  YES, then match YES, Don't know  1 => 1,3
			If memberpref is NO, then match No, Don't know, Not Selected. 2 => 2, 3, 0	 
			*/	
			//If Manglik is not selected in Member Preference, then the value will be 0; If member profile has dosham 3 then do not check manglik condition.
			$ppmanglik=array();
			$ppmanglik=array_unique($memberPartnerInfo['ppmanglik']);
			$key = array_search(3, $ppmanglik); #remove not given condition
			if ($key != "") {
				unset($array[$key]);
			}
			if(in_array(0, $ppmanglik) == false && count($ppmanglik) >= 1) #checking Manglik:Any condition
			{
				if(in_array(1, $ppmanglik) || in_array(2, $ppmanglik))
				{
					$manglik = array();
					if(in_array(1, $ppmanglik)) #Yes
						$manglik = array(1,3);
					if(in_array(2, $ppmanglik)) #No
						$manglik = array_merge($manglik,array(2,3,0));
					$manglik = array_unique($manglik);
					$conditionQry .= " AND (".getINQuery($manglik,"Dosham").")";
				}
			}	

			# Eatinghabits
			/*1-Vegetarian; 2- Non Vegetarian; 3- Eggetarian 
			Eating Habits	Mapping	
			Veg			1 - 1,3		Veg+Egg.	
			Egg			3 - 1,3	    Veg+Egg.	
			Non veg		2 - 2,3		Non-Veg+Egg.*/
			if(in_array(0, $memberPartnerInfo['ppeatinghabitspref']) == FALSE && !empty($memberPartnerInfo['ppeatinghabitspref']))	
			{
				$eatingHabitsPref = array();
				if(in_array(1, $memberPartnerInfo['ppeatinghabitspref']) == TRUE || in_array(3, $memberPartnerInfo['ppeatinghabitspref']) == TRUE)
					$eatingHabitsPref = array_merge($eatingHabitsPref,array(1,3));		//Veg
				if(in_array(2, $memberPartnerInfo['ppeatinghabitspref']) == TRUE)
					$eatingHabitsPref = array_merge($eatingHabitsPref,array(2,3));		//Non-Veg

				$eatingHabitsPref = array_unique($eatingHabitsPref);
				$conditionQry .= " AND (".getINQuery($eatingHabitsPref,"EatingHabits").")";
			}


			# Education 
			if(in_array(0, $memberPartnerInfo['ppmatcheducation']) == FALSE && !empty($memberPartnerInfo['ppmatcheducation'])){
				$conditionQry .= " AND (".getINQuery($memberPartnerInfo['ppmatcheducation'],"EducationSelected").")";
			}

		   # Country  # residingstate 
				if((in_array(0, $memberPartnerInfo['ppcountry']) == FALSE) && ((in_array(98, $memberPartnerInfo['ppcountry']) && in_array(0, $memberPartnerInfo['ppindianstates']) == FALSE && (count($memberPartnerInfo['ppindianstates']) > 1 || (count($memberPartnerInfo['ppindianstates']) == 1 && $memberPartnerInfo['ppindianstates'][0] > 0))) || (in_array(222, $memberPartnerInfo['ppcountry']) && in_array(0, $memberPartnerInfo['ppusstates']) == FALSE && (count($memberPartnerInfo['ppusstates']) > 1 || (count($memberPartnerInfo['ppusstates']) == 1 && $memberPartnerInfo['ppusstates'][0] > 0)))))
				{	
						$res["countryStateCityQuery"] = bmfuncgetMatchCountryQuery($memberPartnerInfo['ppcountry'], $memberPartnerInfo["ppusstates"], $memberPartnerInfo["ppindianstates"], $memberPartnerInfo["ppindiancity"], 0);
						$conditionQry .= " AND (".bmfuncgetMatchCountryQuery($memberPartnerInfo["ppcountry"], $memberPartnerInfo["ppusstates"], $memberPartnerInfo["ppindianstates"], array(0), 0).")";
				}else if(in_array(0, $memberPartnerInfo['ppcountry']) == FALSE)
				{	
					$conditionQry .= " AND (".getINQuery($memberPartnerInfo['ppcountry'],"CountrySelected").")";
				}
			
	   

		   # Citizenship
			if(in_array(0, $memberPartnerInfo['ppcitizenship']) == FALSE && !empty($memberPartnerInfo['ppcitizenship'])){
				$conditionQry .= " AND (".getINQuery($memberPartnerInfo['ppcitizenship'],"Citizenship").")";
			}
		 
	 
		   # ResidentStatus 
			if(in_array(0, $memberPartnerInfo['ppresidentstatus']) == FALSE && !empty($memberPartnerInfo['ppresidentstatus']))
			{
				//IF member select multiple countries say US, India and select ResidentStatus as "Permenant Resident", then we should retrieve members who are ((US and "Permenant Resident") OR India). Coz, India we consider Citizen as default value. Resident status handled in conf.
				if(in_array(0, $memberPartnerInfo["ppcountry"]) == TRUE || in_array(98, $memberPartnerInfo["ppcountry"]) == TRUE)
					$residentstatus = array_merge($memberPartnerInfo['ppresidentstatus'],array(100));
				else
					$residentstatus = $memberPartnerInfo['ppresidentstatus'];	

				$conditionQry .= " AND (".getINQuery($residentstatus,"ResidentStatus").")";
			}

			# Gender
			if($conditionQry!="" && $gender!=""){
					$matchGender=($gender=='F')?1:0;
					$conditionQry .= " AND (Gender=".$matchGender.")";
			}elseif($gender!=""){
					$matchGender=($gender=='F')?1:0;
					$conditionQry .= "(Gender=".$matchGender.")";
			}
			$conditionQry=ltrim($conditionQry,' AND'); //Removing space with AND if found begining of condition
		}
		$resultArr[0] = isset($res)?$res:'';
		$resultArr[1] = $conditionQry;
		return $resultArr;	
}
function getINQuery($arrayInfo,$field)
{
	$qry="";
	$inStr=implode(',',$arrayInfo);
	if($inStr){
		$qry="IN(".$field.",".$inStr.")";
	}
	return $qry;
}
function set_income($st_income, $end_income, $type)
{
	global $ANNUALINCOMEINRVALUEHASH, $MAXINCOMESEARCH, $ANNUALINCOMEDOLLARVALUEHASH;

	$USD4CONVERSION = 46;
	$output = false;
	if($type == 1){ #INR Calculation
		if($st_income == 1)	
		{
			$output[0] = 1;
			$output[1] = $ANNUALINCOMEINRVALUEHASH[1];
		}
		else if($st_income == 29)
		{
			$output[0] = $ANNUALINCOMEINRVALUEHASH[29];
			$output[1] = $MAXINCOMESEARCH;
		}
		else if($st_income >= 2 && $st_income <= 28)
		{
			$output[0] = $ANNUALINCOMEINRVALUEHASH[$st_income];
			$output[1] = $ANNUALINCOMEINRVALUEHASH[$end_income];

			if($st_income > $end_income && $end_income > 0)	//If form is submitted with invalid range
			{
				$output[0] = $ANNUALINCOMEINRVALUEHASH[$end_income];
				$output[1] = $ANNUALINCOMEINRVALUEHASH[$st_income];
			}
			if($st_income >= 2 && $end_income == 0)	
			{ #If member select a value in first dropdown and ANY in second dropdown
				$output[0] = $ANNUALINCOMEINRVALUEHASH[$st_income];
				$output[1] = $MAXINCOMESEARCH;
			}					
		}
	}
	else if($type == 2){ #USD Calculation
		if($st_income == 1)	
		{ #If member select Less than 50000
			$output[0] = 1;
			$output[1] = $ANNUALINCOMEDOLLARVALUEHASH[1]*$USD4CONVERSION;
		}
		else if($st_income == 16)
		{
			$output[0] = $ANNUALINCOMEDOLLARVALUEHASH[16]*$USD4CONVERSION;
			$output[1] = $MAXINCOMESEARCH;
		}
		else if($st_income >= 2 && $st_income <= 15)
		{
			$output[0] = $ANNUALINCOMEDOLLARVALUEHASH[$st_income]*$USD4CONVERSION;
			$output[1] = $ANNUALINCOMEDOLLARVALUEHASH[$end_income]*$USD4CONVERSION;
			
			if($st_income > $end_income && $end_income > 0)	//If form is submitted with invalid range
			{
				$output[0] = $ANNUALINCOMEDOLLARVALUEHASH[$end_income]*$USD4CONVERSION;
				$output[1] = $ANNUALINCOMEDOLLARVALUEHASH[$st_income]*$USD4CONVERSION;
			}
			if($st_income >= 2 && $end_income == 0)	//If member select a value in first dropdown and ANY in second dropdown
			{
				$output[0] = $ANNUALINCOMEDOLLARVALUEHASH[$st_income]*$USD4CONVERSION;
				$output[1] = $MAXINCOMESEARCH;
			}			
		}
	}
	return $output;
}
function recentupdategetPhotoInfo($ids,$dbcon) { 
	global $DBNAME,$MERGETABLE;

	$photo_array="";
	$sql = array('MatriId','ThumbImg1','ThumbImgs1');

	foreach($ids as $value) {
		$strFields .= "?,";
		$whereVal[0] = 'MatriId';
		$whereVal[1] = $value;

		$whereClauseVal[] = $whereVal;
	}

	$strFields = trim($strFields,',');
	$whereClause = "MatriId in (".$strFields.")";

	$res = $dbcon->bmDbSelect($DBNAME['MATRIMONYMS'],$MERGETABLE["PHOTOINFO"], $sql, $whereClause, $whereClauseVal);
	if ($res[0] != '') {
		$dbcon->bmDbFetchArray($res[0],$row);	
		foreach($row as $val) {
			$photo_array[strtoupper($val['MatriId'])] = $val;
		}
	}
	return $photo_array;
}

?>
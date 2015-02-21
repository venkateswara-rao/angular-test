var paymentConfig = {
    xhrUrl : 'https://bharatmatrimony.com/payments/testingtool/xhr/xhr.php',
    urlPaths : {
        home : '/',
        freeId: '/free_id',
        paidId : '/paid_id',
        removeOffer : '/removeoffer',
        downgradeId : '/downgrade_id'
    },
    loadingColor : 'red'
}

var myApp = angular.module('app',['ngRoute','ngProgress']);

myApp.config(function($routeProvider){

  $routeProvider
    .when('/',{
        templateUrl:'views/home.html',
        controller:'homeController'
    })
    .when('/removeoffer',{
        templateUrl:'views/remove-offer.html',
        controller:'removeOfferController'
    })
    .when('/free_id',{
        templateUrl:'views/free-paid-id.html',
        controller:'freePaidIdcontroller'
    })
    .when('/paid_id',{
        templateUrl:'views/free-paid-id.html',
        controller:'freePaidIdcontroller'
    })
    .when('/downgrade_id',{
        templateUrl:'views/downgrade-id.html',
        controller:'downgradeIdController'
    })
    .otherwise({redirectTo:'/'});

});

myApp.controller('homeController',function($scope){

    $scope.message = 'This interface will fetch free,paid MatriId from local server..';
    
});

myApp.controller('freePaidIdcontroller',function($scope,$location,$http,ngProgress){
      
    var type='';
    
    if($location.path()==='/free_id'){
      $scope.memberShipType = 'Free';
      type='Free';
      typeObj='F';
    }else{
      $scope.memberShipType = 'Paid';
      type='Paid';
      typeObj='R';
    }
    
    $scope.showHideTable = false;
 
    $scope.getIdsMaleFemale = function(id){
    
      genderObj = (id==1)?'M':'F';
      
      ngProgress.color(paymentConfig.loadingColor);
      ngProgress.start();
      
      $http
          .get(paymentConfig.xhrUrl,{params:{option:1,limit:20,entryType:typeObj,gender:genderObj}},{method:'POST'})
          .success(function(data){
            $scope.showHideTable = true;
            $scope.freeIds = data.success;
            ngProgress.complete();
      });
        
    }
    
    $scope.getIdsMaleFemaleLimit = function(){
    
      ngProgress.color(paymentConfig.loadingColor);
      ngProgress.start();
      
      genderObj = ($scope.freeMaleFemale==1)?'M':'F';
      matriIdLimit = $scope.idLimit;
      
       $http
          .get(paymentConfig.xhrUrl,{params:{option:1,limit:matriIdLimit,entryType:typeObj,gender:genderObj}},{method:'POST'})
          .success(function(data){
            $scope.showHideTable = true;
            $scope.freeIds = data.success;
            ngProgress.complete();
      });
      
    }
    
    $scope.EntryTypeShow = function(data){
        
        if(typeof(data)!='undefined'){
            
            return (data==='F')?'Free Member':'Paid Member';
            
        }
    }
    $scope.GenderShow = function(data){
        
        if(typeof(data)!='undefined'){
            
            return (data==='M')?'Male':'Female';
            
        }
    }
});

myApp.controller('downgradeIdController',function($scope,$http,ngProgress){
 
  $scope.getDownOffer = function(){
  
      ngProgress.color(paymentConfig.loadingColor); 
      ngProgress.start();
      $http
          .get(paymentConfig.xhrUrl,{params:{option:3,matriId:$scope.downOffer}},{method:'POST'})
          .success(function(data){
           $scope.downOfferResponse = data.success;
           ngProgress.complete();
           $scope.downOffer='';
      });
        
  }

});


myApp.controller('removeOfferController',function($scope,$http,ngProgress){

  $scope.getRemoveOffer = function(){
  
      ngProgress.color(paymentConfig.loadingColor);
      ngProgress.start();
      
      $http
          .get(paymentConfig.xhrUrl,{params:{option:2,matriId:$scope.removeOffer}},{method:'POST'})
          .success(function(data){
           $scope.removeXhrResponse = data.success;
           ngProgress.complete();
           $scope.removeOffer='';
      });
        
  }

});

myApp.controller('menuActiveController',function($scope,$location){

  $scope.isActive = function(route){
    
    if(($location.path() === '/paid_id' || $location.path() === '/free_id') && route==='/')
    {
        return true;
    }
    return route === $location.path();
    
  };
  
});

myApp.controller('sidebarController',function($location,$scope){
        
  $scope.checkActiveMenu = function(){
      
      if(typeof($location.path())!='undefined'){
      
        var currentPath = $location.path();
      
        if(currentPath == '/' || currentPath == '/free_id' || currentPath == '/paid_id'){
          return '/';
        }
        if(currentPath == '/removeoffer' || currentPath == '/downgrade_id'){
          return '/removeoffer';
        }
      }
      
  }

});



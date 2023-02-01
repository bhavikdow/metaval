<?php

namespace App\Models\front;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class FrontModel extends Model
{
    use HasFactory;

    public function getUserDetail($user_id){
        return DB::table('users')->where('user_id', $user_id)->get();
    }

    public function getUser(){
        return DB::table('users')->first();
    }
    
    public function getCurrency($region){
        return DB::table('region')->where('region_id', $region)->get()->first();
    }
    
    public function getCountryIdByName($country){
        $data = DB::table('region')->where('region_name', $country)->get()->first();
        if(empty($data)){
            $data1 = DB::table('region')->where('region_name', 'global')->get()->first();
            return $data1;
        }else{
            return $data;
        }
    }

    public function doSignUp($RequestData){
        DB::enableQueryLog();
        $data = array(
            'name'          => $RequestData['name'],
            'email'         => $RequestData['email'],
            'password'      => hash('sha256', $RequestData['password']),
            'region'        => session()->get('front_region'),
        );
        DB::table('users')->insert($data);
        return DB::getPdo()->lastInsertId();
    }

    public function check_login($formdata){
       // DB::enableQueryLog();
        $login = array(
            'email'    => $formdata['email'],
            'password' => hash('sha256', $formdata['password']),
        );
        return DB::table('users')->where($login)->where('status', '!=', 'Deleted')->get()->first();        
    }

    public function resetPassowrd($RequestData, $user_id) {
        $data = array(
            'password' => hash('sha256',$RequestData['newPassword']),
        );
        return DB::table('users')->where('user_id', $user_id)->update($data);
    }
    
    public function insertCartQuantity($product_id, $user_id, $qty) {
        $data = array(
            'product_id' => $product_id,
            'user_id'    => $user_id,
            'qty'        => $qty,
            'region'        => session()->get('front_region'),
        );
        DB::table('cart')->insert($data);
        return true;
    }
    
    public function updateCartQuantity($cart_id, $qty) {
        $data = array(
            'qty' => $qty,
        );
        return DB::table('cart')->where('cart_id', $cart_id)->update($data);
    }

    public function editProfileSettings($RequestData, $user_id){
        $data = array(
            'name'   => $RequestData['name'],
            'mobile' => $RequestData['mobile'],
            'country_code' => $RequestData['country_code'],
        );
        return DB::table('users')->where('user_id', $user_id)->update($data);
    }
    
    public function getphonecode(){
        return DB::table('countries')->groupBy('phonecode')->orderBy('phonecode','asc')->get();
    }

    public function getUserAddressDetails($user_id){
        return DB::table('user_address')->select('user_address.*','countries.name as country_name','states.name as state_name','cities.name as city_name')
        ->join('countries', 'user_address.country', '=', 'countries.id')
        ->join('states', 'user_address.state', '=', 'states.id')
        ->join('cities', 'user_address.city', '=', 'cities.id')
        ->where('user_id', $user_id)->orderBy('user_id','desc')->get();
    }

    public function doAddAddress($RequestData, $user_id){
        $data = array(
            'user_id'     => $user_id,
            'name'        => $RequestData['name'],
            'mobile'      => $RequestData['mobile'],
            'address'     => $RequestData['address'],
            'landmark'    => $RequestData['landmark'],
            'country_code'=> $RequestData['country_code'],
            'country'     => $RequestData['country_id'],
            'state'       => $RequestData['state_id'],
            'city'        => $RequestData['city_id'],
            'postal_code' => $RequestData['postal_code'],
            'region'        => session()->get('front_region'),
        );
        DB::table('user_address')->where('user_id', $user_id)->insert($data);
        return DB::getPdo()->lastInsertId();
    }

    public function getAllCountries(){
        return DB::table('countries')->get();
    }

    public function getStatesByCountryId($country_id){
        return DB::table('states')->where('country_id', $country_id)->get();
    }

    public function getCitiesByStateId($state_id){
        return DB::table('cities')->where('state_id', $state_id)->get();
    }

    // Home Page Setting 
    public function getSettingByType($type, $region){
        return $data=DB::table('setting')->where(['type' => $type, 'region' => $region])->first();
    }
    
    public function getAboutPage($type){
        return $data=DB::table('about_page')->where('type',$type)->where('status','Active')->get();
    }

    public function getAllServices(){
        return DB::table('services')->where('status','=','Active')->orderByDesc('service_id')->get();
    }

    public function getAllBrands(){
        return DB::table('brands')->where('status','Active')->orderBy('order', 'asc')->get();
    }
    
    public function getAllGroupByBrands(){
        return DB::table('brands')->where('status','Active')->groupBy('brand_name')->orderByDesc('brand_id')->get();
    }
    
    public function getInterestedProduct(){
        return DB::table('interested_product')->select('*')->where('status', 'Active')->get();
    }
    
    public function addDistributor($RequestData){
        $data = array(
            'name'          => $RequestData['name'],
            'email'         => $RequestData['email'],
            'company_name'  => $RequestData['company_name'],
            'country_code'    => $RequestData['country_code'],
            'contact_no'    => $RequestData['contact_no'],
            'interested_product_id'    => $RequestData['interested_product_id'],
            'address'       => $RequestData['address'],
            'description'   => $RequestData['description'],
            'region'        => session()->get('front_region'),
        );
        return DB::table('distributor')->insert($data);
        return DB::getPdo()->lastInsertId();
    }

    public function doContactUs($RequestData){
        $data = array(
            'name'          => $RequestData['name'],
            'email'         => $RequestData['email'],
            'company_name'  => $RequestData['company_name'],
            'country_code' => $RequestData['country_code'],
            'contact_no'    => $RequestData['contact_no'],
            'address'       => $RequestData['address'],
            'description'   => $RequestData['description'],
            'region'        => session()->get('front_region'),
        );
        DB::table('contact_us')->insert($data);
        return DB::getPdo()->lastInsertId();
    }
    //----------------------------------Product---------------------------------------------------
    public function getProducts(){
        return DB::table('products')
            ->where('status','Active')
            ->get();
    }
    
    public function getProductByKeyWordsBrandCategory($keywords, $brand_id, $category_id){
        $data = DB::table('products')
            ->where('product_name','LIKE','%'.$keywords.'%')
            ->where('status', 'Active');
            if(!empty($brand_id[0])){
                $data->whereIn('brand_id', $brand_id);
            }
            if(!empty($category_id[0])){
                $data->whereIn('category_id', $category_id);
            }
            return $data->get();
    }

    public function getProductByApplicationAndMaterial($product_id){
        $data = DB::table('products')
                ->whereIn('product_id', $product_id);
            return $data->get();
    }
    
    public function getProductByApplication($application_id){
        $data = DB::table('product_applications')
                ->select('product_id')
                ->whereIn('application_id', $application_id);
            return $data->get();
    }

    public function getProductByMaterial($material_id){
        $data = DB::table('product_materials')
                ->select('product_id')
                ->whereIn('material_id', $material_id);
            return $data->get();
    }

    public function getBrandIdByProductId($product_id){
        $data = DB::table('products')
                ->select('brand_id')
                ->whereIn('product_id', $product_id);
            return $data->get();
    }
    
    public function getProductByKeyWords($keywords){
        return DB::table('products')->where('product_name','LIKE','%'.$keywords.'%')->where('status','Active')->orderBy('product_id', 'desc')->get();
    }

    public function getAllReview(){
        return DB::table('product_review')
        ->where('status','Active')
        ->get();
    }

    public function getReviewByProductId($pid){
        return DB::table('product_review')
        ->where('product_id',$pid)
        ->where('status','Active')
        ->get();
    }
    
    public function getBanners($type){
        return DB::table('banners')
            ->where('status','Active')
            ->where('type',$type)
            ->get();
    }

    public function submitReview($RequestData, $product_id, $user_id){
        $data = array(
            'name'          => $RequestData['name'],
            'email'         => $RequestData['email'],
            'review'        => $RequestData['message'],
            'product_id'    => $product_id,
            'user_id'       => $user_id,
            'rating'        =>$RequestData['rating'],
            'region'        => session()->get('front_region'),
        );
        DB::table('product_review')->insert($data);
        return DB::getPdo()->lastInsertId();
    }

    //--------------------------------------------------------Forgot Password------------------------------------------
    public function doResetPassword($email, $password) {
        return  DB::table('users')->where('email', $email)->update(['password' => $password]);
    }
      
    public function getUserByEmail($email) {
        return DB::table('users')->where('email', $email)->first();
    }
      
    public function forgetPasswordLinkValidity($user_id) {         
        $data = array(
            'user_id'  => $user_id,
            'status'   => '0',
            'region'        => session()->get('front_region'),
        );
        DB::table('user_forgot_password')->insert($data);
        $id = DB::getPdo()->lastInsertId();
        return DB::table('user_forgot_password')->where('user_forgot_password_id',$id)->get()->first();
    }
      
    public function linkValidity($user_id) {
        return DB::table('users')->where('user_id' ,$user_id)->update(['forgot_password_status' => '1']);
    }
      
    public function getLinkValidity($user_id){  
        return DB::table('users')->where('user_id',$user_id)->get()->first();    
    }
      
    public function doForgotPassword($id,$newpassword) {
        return DB::table('users')->where('user_id',$id)->update(['password' => $newpassword]);
    }

    public function sendOtp($otp, $user_id){
        $otpexpiry = date('Y-m-d h:i:s', strtotime('+5 minutes', strtotime(date('Y-m-d h:i:s'))));
        $data = array(
            'otp' => $otp,
            'otp_expiry' => $otpexpiry,
        );
        return DB::table('users')->where('user_id', $user_id)->update($data);
    }

    public function verifyOtp($otp, $user_id){
        return DB::table('users')->select('user_id','otp','otp_expiry')->where('user_id', $user_id)->where('otp',$otp)->get()->first();
    }

    public function updateVerifyStatus($user_id){
        return DB::table('users')->where('user_id',$user_id)->update(['is_verified'=>'yes']);
    }

    //-------------------------------------------Request A qoute---------------------------------------------------------
    public function getCategoryByBrandIDS($brandis){
        return DB::table('categories')
        ->whereIn('brand_id',$brandis)
        ->where('status','Active')
        ->get();
    }
    public function getSubcategoryByBrandidAndCategoryId($arr){
       // DB::enableQueryLog();
        $data=DB::table('sub_categories');
        $data->Where(function($query) use($arr,$data){
                if(!empty($arr)){
                    foreach($arr as $row){
                        $data->orWhere(function($query) use($row){
                            $query->where($row);
                        });
                    }
                }
        });
        return $data->get();
        //dd(DB::getQueryLog());
    }

    public function getProductsRequestaquote($arr){
         //DB::enableQueryLog();
         $data=DB::table('products');
         $data->Where(function($query) use($arr,$data){
                 if(!empty($arr)){
                     foreach($arr as $row){
                         $data->orWhere(function($query) use($row){
                             $query->where($row);
                         });
                     }
                 }
         });
         return $data->get();
         //dd(DB::getQueryLog());
     }

     public function getProdutsByProductsIds($data){
        return DB::table('products')->where('status','Active')->whereIn('product_id',$data)->get();
     }

     public function getProductDimentions($pid){
        return DB::table('product_dimension')
        ->join('products','product_dimension.product_id','=','products.product_id')
        ->where('product_dimension.status','Active')->where('product_dimension.product_id',$pid)->get();
     }
     public function getProductDimentionsByid($id){
        return DB::table('product_dimension')->where('status','Active')->where('id',$id)->get()->first();
     }
     public function addPersonalDetails($data){
        $finaldata=array(
            'name'           => $data['name'],
            'country_code'   => $data['country_code'],
            'email'          => $data['email'],
            'country_code'   => $data['country_code'],
            'phone'          => $data['phone_number'],
            'company_name'   => $data['company_name'],
            'comment'        => $data['description'],
            'region'        => session()->get('front_region'),
        );
        DB::table('lead')->insert($finaldata);
        return DB::getPdo()->lastInsertId();
     }

    public function insertRequestQuoteDetails($data){
        DB::table('leadproduct')->insert($data);
        return DB::getPdo()->lastInsertId();
    }
    
   // cart Functinality
    public function addtocart($qty,$product_id,$user_id=null){
        $data=array(
            'qty'            => $qty,
            'product_id'     => $product_id,
            'user_id'        => $user_id,
            'region'        => session()->get('front_region'),
        );
        DB::table('cart')->insert($data);
        return DB::getPdo()->lastInsertId();
    }
    public function updateCart($qty,$cart_id){
        $data=array(
            'qty'            => $qty,
        );
        DB::table('cart')->where('cart_id',$cart_id)->update($data);   
        return true;
    }

    public function getCartByUserId($product_id,$user_id){
        return DB::table('cart')->where('product_id',$product_id)->where('user_id',$user_id)->get()->first();
    }

    public function getUserCart($user_id){
        return DB::table('cart')
            ->join('products','cart.product_id','=','products.product_id')
            ->where('cart.user_id',$user_id)
            ->orderBy('cart_id','asc')
            ->get();
    }

    //=========================================================Cart Manangement======================================================
    public function getCartData($user_id){
        return DB::table('cart')->select('cart.*','products.product_name','products.product_image','products.product_price','products.product_quantity')
        ->join('products','products.product_id','cart.product_id')
        ->where('user_id',$user_id)->where('qty' ,'!=', '0')->get();
    }

    public function upadateQty($data, $product_id) {
        return DB::table('cart')->where('product_id', $product_id)->update($data);
    }

    public function addCartdb($data) {
        DB::table('cart')->insert($data);
        return DB::getPdo()->lastInsertId();
    }
    public function getProductImageByID(){
        // here to imaplement
    }

    public function getProductManagementByID(){
        // here to imaplement
    }
    
    public function getProductsByProductId($product_id){
    return DB::table('products')->where('product_id', $product_id)
        ->where('status','Active')
        ->get()->first();
    }


    public function getSimilarProducts($product_ids){
        return DB::table('products')
            ->whereNotIn('product_id',$product_ids)
            ->get();
    }
    
    public function getTwitterLink(){
        return DB::table('social')->select('social_link')->where('social_name', 'Twitter')->get();
    }

    public function getLinkedInLink(){
        return DB::table('social')->select('social_link')->where('social_name', 'LinkedIn')->get();
    }

    public function getFacebookLink(){
        return DB::table('social')->select('social_link')->where('social_name', 'Facebook')->get();
    }
    

}

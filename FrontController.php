<?php

namespace App\Http\Controllers\front;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\admin\AdminModel;
use App\Models\admin\BrandModel;
use App\Models\admin\ProductModel;
use App\Models\admin\CategoryModel;
use App\Models\front\FrontModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Cookie;
use Illuminate\Support\Facades\URL;

class FrontController extends Controller
{
    //--------------------------------- DB Instance---------------------------------
    private $admin_model;
    private $front_model;
    private $brand_model;
    private $product_model;
    private $category_model;
    private $session_region;
    public $rc;

    public function __construct(){
        $this->admin_model = new AdminModel();
        $this->front_model = new FrontModel();
        $this->brand_model = new BrandModel();
        $this->product_model = new ProductModel();
        $this->category_model = new CategoryModel();
        $this->session_region = !empty(session()->get('front_region')) ? session()->get('front_region') : 1;
        $currnet=URL::current();
        $resutl=array("http://","https://");
        $current=@str_replace($resutl,'',$currnet);
        $segment=@explode('/',$current);
        $domain=@$segment[1];
        //dd($domain);
        $this->rc=$domain;
    }

    public function user_detail(){
        $user_detail = $this->front_model->getUserDetail(session()->get('mat_user_id'))->first();
        return $user_detail;
    }
    
    public function onChangeWebsiteRedirect(Request $request){
        $redirect_url = $request->post('redirect_url');
        $country_name = $request->post('country_name');
        $country_id = $this->front_model->getCountryIdByName(lcfirst($country_name));
        session()->put('front_region', $country_id->region_id);
        if(!empty($country_name)){
            setcookie('country_id', $country_name, time() + (86400 * 30), "/");
        }
        $session = @$_COOKIE['country_id'];
        return response()->json(['result' => 1, 'url' => $redirect_url]);
    }
    
    private function loadview($view, $data = NULL){
        $data['country_details'] = ip_info();

        $country_id = $this->front_model->getCountryIdByName(strtolower($data['country_details']->country_name));
        if(empty(session()->get('front_region'))){
            session()->put('front_region', $country_id->region_id);
        }
        $country_redirect = @$_COOKIE['country_id'];
        if(empty($country_redirect) || $country_redirect == 'Australia' || $country_redirect == 'India'){
            setcookie('country_id', $data['country_details']->country_name, time()+ 1800);
            $country_redirect = isset($_COOKIE['country_id'])?$_COOKIE['country_id']:$data['country_details']->country_name;
            $data['country_redirect'] = $country_redirect;
        }else{
            $data['country_redirect'] = '';
        }
        $data['currency'] = $this->front_model->getCurrency($this->session_region);
        $data['user_detail'] = $this->user_detail();
        $data['all_regions'] = $this->admin_model->getAllRegions();
        $country_redirect=$this->rc;
        $data['regions'] = $this->admin_model->getAllRegionsAndWebsite($data['country_redirect']);
        // $data['regions'] = $this->admin_model->getAllRegionsAndWebsiteV2($country_redirect);
        if(empty($data['regions'])){
            $data['regions'] = $this->admin_model->getAllRegionsAndWebsite('Global');
        }
        if(!empty($data['all_regions'])){
            $i = 0;
            foreach($data['all_regions'] as $value){
                $region_name[$i] = ucfirst($value->region_name);
                $i++;
            }
        }
        $data['brands'] = $this->front_model->getAllBrands()->where('region', $this->session_region);
        $i=0;
        foreach($data['brands'] as $row){
            $temp=$this->category_model->getAllCategories($row->brand_id)->where('region', $this->session_region);
            $row->category=$temp;
            @$row->sub_categories =$this->category_model->get_subcategory_by_cid($temp[0]->category_id);
        }
        $data['headersubcategory']=$data['brands']->first()->sub_categories;
        $data['headercategory']=$data['brands']->first()->category;
       // dd($data['brands']); die;
        $data['getTwitterLink'] = $this->front_model->getTwitterLink();
        $data['twitter_link'] = $data['getTwitterLink']->first()->social_link;
        $data['getLinkedInLink'] = $this->front_model->getLinkedInLink();
        $data['linkedin_link'] = $data['getLinkedInLink']->first()->social_link;
        $data['getFacebookLink'] = $this->front_model->getFacebookLink();
        $data['facebook_link'] = $data['getFacebookLink']->first()->social_link;
        $data['contact_detail'] = $this->admin_model->getContactDetail()->first();
        $data['logo'] = $this->admin_model->getLogo($this->session_region);
        // dd($data['logo']);
        $data['testimonials'] = $this->admin_model->getTestimonials($this->session_region);
        $data['categories'] = $this->category_model->getAllCategories(null)->where('region', $this->session_region);
        $data['cart'] = $this->getCartItem();
        $data['region_id'] = $this->session_region;
        //--------------------------------Cart Count----------------
        $is_login = $this->isLogin();
        $sessionCart = session()->get('isNotLogin');
        $usercartdata = $this->front_model->getCartData($is_login)->groupBy('product_id')->toArray();
        if(!empty($sessionCart)){
            $data['cartcount'] = session()->get('isNotLogin');
            unset($data['cartcount'][""]);
            $data['cart_count'] = count($data['cartcount']);
        }
        if(!empty($usercartdata)){
            $data['cartcount'] = $this->front_model->getCartData(session()->get('mat_user_id'))->where('region', $this->session_region);
            $data['cart_count'] = count($data['cartcount']);
        }
        //----------------------------------------------------------
        return view('front/' . $view, $data);
    }
    
    public function globalProductSearchWrapper(Request $Request){
        $keywords = $Request->post('keyword');
        $data['products'] = $this->front_model->getProductByKeyWords($keywords)->where('region', $this->session_region);
        $htmlwrapper = view('front/common/wrapper/global_product_search_wrapper', $data)->render();
        return response()->json(['result' => 1, 'msg' => 'Product found...', 'htmlwrapper' => $htmlwrapper]);
    }

    // ------------------------ Sign Up -------------------------------------------------
    public function signup(){
        $data['title'] = 'Sign Up';
        return $this->loadview('user/signup', $data);
    }

    public function doSignUp(Request $Request){
        $RequestData = $Request->all();
        $user_detail = $this->user_detail();
        $validator = Validator::make($RequestData, $rules = [
            'email' => 'required|email',
            'name' => 'required|regex:/^[\pL\s\-]+$/u',
            'password' => 'required|min:6',
            'confirmPassword' => 'required|min:6|same:password',
        ], $messages = [
            'required' => 'The :attribute field is required.',
        ]);
        if ($validator->fails()) {
            return response()->json(['result' => 0, 'errors' => $validator->errors()]);
            return false;
        }
        $result = $this->front_model->doSignUp($RequestData);
        if ($result) {
            $result = $this->sendSignUpVerificationMail($result);
            return response()->json(['result' => 1, 'url' => route('front/index'), 'msg' => 'Registration Successfully, Verification mail has been sent on your email ID.', 'data' => $result]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'Registration Failed!!!']);
        }
    }

    public function sendSignUpVerificationMail($user_id)
    {
        $user_detail = $this->front_model->getUserDetail($user_id);
        $encrypted_id = substr(uniqid(), 0, 10) . $user_detail->first()->user_id . substr(uniqid(), 0, 10);
        $htmlContent = "<h3>Dear " . $user_detail->first()->name . ",</h3>";
        $htmlContent .= "<div style='padding-top:8px;'>Please click the following link to verify yourself.</div>";
        $htmlContent .= "<a href='" . route('front/verifyemail', ['user_id' => $encrypted_id]) . "'> Verify Yourself!!</a>";
        $from = "admin@metaval.com";
        $to = $user_detail->first()->email;
        $subject = "[Metaval] Signup Verification";
        $headers = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
        $headers .= 'From: ' . $from . "\r\n";
        @mail($to, $subject, $htmlContent, $headers);
        return FALSE;
    }

    public function verifyEmail($user_id)
    {
        $user_id=decryptionID($user_id);
        update('users','user_id',$user_id,['is_verified' =>'yes']);
        $data['title'] = 'Thank You!';
        return $this->loadview('user/verifyemail', $data);
    }

    //------------------Login -------------------------------------------------------

    public function login()
    {
        $data['title'] = 'Login';
        $data['user_detail'] = $this->user_detail();
        return $this->loadview('user/login', $data);
    }

    public function check_login(Request $request)
    {
        $form_data = $request->all();
        $validator = Validator::make($form_data, $rules = [
            'email' => 'required',
            'password' => 'required',
        ], $messages = [
            'required' => 'The :attribute field is required.',
        ]);
        if ($validator->fails()) {
            return response()->json(['result' => 0, 'errors' => $validator->errors()]);
            return false;
        }
        $user_detail = $this->front_model->check_login($form_data);
        //dd($user_detail);
        if (!empty($user_detail)) {
            if ($user_detail->is_verified == 'no') {
                // resend mail code
                $this->sendSignUpVerificationMail($user_detail->user_id);
                return response()->json(['result' => -1, 'msg' => 'Please Verify your E-mail ID.!!']);
            }
            if ($user_detail->status == 'Inactive') {
                return response()->json(['result' => -1, 'msg' => 'Your account has been Inactive by the Admin']);
            }
            if ($user_detail->status == 'Deleted') {
                return response()->json(['result' => -1, 'msg' => 'Your account has been Deleted by the Admin']);
            }
            if ($user_detail->email != $form_data['email'] && $user_detail->password != hash('sha256', $form_data['password'])) {
                return response()->json(['result' => -1, 'msg' => 'Please Enter Valid Email and Password.']);
            } elseif ($user_detail->email != $form_data['email']) {
                return response()->json(['result' => -1, 'msg' => 'Please Enter Valid Email.']);
            } elseif ($user_detail->password != hash('sha256', $form_data['password'])) {
                return response()->json(['result' => -1, 'msg' => 'Please Enter Valid Password.']);
            } else {
                $request->session()->put(['mat_user_id' => $user_detail->user_id]);
                $this->tempUserCartData();
                return response()->json(['result' => 1, 'msg' => 'Loading! Please Wait..!!', 'url' => route('front/shop')]);
            }
        } else {
            return response()->json(['result' => -1, 'msg' => 'Please Enter Valid Email or Password.']);
        }
    }

    //--------------- Logout ---------------------------------------------------------------
    public function logout(Request $request)
    {
        $request->session()->forget('mat_user_id');
        session()->forget('isNotLogin');
        return response()->json(['result' => 1, 'msg' => 'Logged out...', 'url' => route('front/login')]);
    }

    // ------------ Change Password --------------------------------------------------------
    public function changePassword()
    {
        $data['title'] = 'Change Password';
        return $this->loadview('user/change_password', $data);
    }

    public function doChangePassword(Request $Request)
    {
        $RequestData = $Request->all();
        $user_id = $Request->session()->get('mat_user_id');
        $validator = Validator::make($RequestData, $rules = [
            'password' => 'required',
            'newPassword' => 'required|min:6',
            'confirmNewPassword' => 'required|min:6|same:newPassword',
        ], $messages = [
            'required' => 'The :attribute field is required.',
        ]);
        if ($validator->fails()) {
            return response()->json(['result' => 0, 'errors' => $validator->errors()]);
            return false;
        }
        $user_detail = $this->user_detail();
        if (!empty($user_detail)) {
            if ($user_detail->password === hash('sha256', $Request->post('newPassword'))) {
                return response()->json(['result' => -1, 'msg' => 'New password and Old password must not be same.']);
            }
        }
        if ($RequestData['newPassword'] === $RequestData['confirmNewPassword']) {
            $changed = $this->front_model->resetPassowrd($RequestData, $user_id);
            if ($changed) {
                return response()->json(['result' => 1, 'url' => route('front/changepassword'), 'msg' => 'Password changed successfully.']);
            } else {
                return response()->json(['result' => 1, 'url' => route('front/changepassword'), 'msg' => 'No changes were found.']);
            }
        } else {
            return response()->json(['result' => -1, 'msg' => 'New password and Confirm Password should be same.']);
        }
    }

    // --------------- Index ---------------------------------------------------------------
    public function index($brand_id = NULL, $category_id = NULL){
        $data['title'] = 'Dashboard';
        $brand_id = decryptionID($brand_id);
        $category_id = decryptionID($category_id);
        $data['user_detail'] = $this->user_detail();
        $data['brands'] = $this->front_model->getAllBrands()->where('region', $this->session_region);
        $data['categories'] = $this->category_model->getAllCategories($brand_id)->where('region', $this->session_region);
        $data['sub_categories'] = $this->category_model->getAllSubCategoriesByCategoryId($brand_id, $category_id)->where('region', $this->session_region);
        $data['banners'] = $this->front_model->getBanners('home')->where('region', $this->session_region);
        $data['about'] = $this->front_model->getAboutPage('homepageabout')->where('region', $this->session_region)->first();
        $data['products'] = $this->product_model->getAllProducts()->where('region', $this->session_region)->where('is_featured', 'Yes');
        $data['services'] = $this->front_model->getAllServices()->where('region', $this->session_region);
        return $this->loadview('index', $data);
    }

    public function subheaderCategoryWrapper(Request $request){
        $brand_id = $request->post('brand_id');
        $data['brands']=$this->front_model->getAllBrands()->where('region', $this->session_region);
        $data['categories'] = $this->category_model->getAllCategories($brand_id)->where('region', $this->session_region);
        $category_id = $this->category_model->getAllCategories($brand_id)->first()->category_id;
        $data['sub_categories'] = $this->category_model->getAllSubCategoriesByCategoryId($brand_id, $category_id)->where('region', $this->session_region);
        $data['activeClass'] = 'groupHover2';
        $htmlwrapper = view('front/common/wrapper/subheadercategorywrapper',$data)->render();
        $htmlwrapper1 = view('front/common/wrapper/subheadersubcategorywrapper',$data)->render();
        return response()->json(['result' => 1, 'htmlwrapper' => $htmlwrapper,'htmlwrapper1' => $htmlwrapper1]);
        
    }

    public function subheaderSubCategoryWrapper(Request $request){
        $brand_id = $request->post('brand_id');
        $category_id = $request->post('category_id');
        $data['sub_categories'] = $this->category_model->getAllSubCategoriesByCategoryId($brand_id, $category_id)->where('region', $this->session_region);
        $htmlwrapper = view('front/common/wrapper/subheadersubcategorywrapper',$data)->render();
        return response()->json(['result' => 1, 'htmlwrapper' => $htmlwrapper]);
        
    }

    public function aboutUs(Request $Request)
    {
        $data['title'] = 'About Us';
        $data['brands'] = $this->front_model->getAllBrands()->where('region', $this->session_region);
        $data['about_bottom'] = $this->front_model->getAboutPage('bottom')->where('region', $this->session_region);
        $data['about_top'] = $this->front_model->getAboutPage('top')->where('region', $this->session_region)->first();
        return $this->loadview('about', $data);
    }

    // -------------------- Become A Distributor ----------------------------
    public function becomeDistributor(){
        $data['title'] = 'Become Distributor';
        $data['country_codes'] = $this->front_model->getphonecode();
        $data['interested_product'] = $this->front_model->getInterestedProduct()->where('region', $this->session_region);
        return $this->loadview('become_distributor', $data);
    }

    public function addDistributor(Request $Request)
    {
        $RequestData = $Request->all();
        $validator = Validator::make($RequestData, $rules = [
            'name'         => 'required|regex:/^[\pL\s\-]+$/u',
            'email'        => 'required',
            'company_name' => 'required|regex:/^[\pL\s\-]+$/u',
            'contact_no'   => 'required|max:15',
            'interested_product_id'   => 'required',
            'address'      => 'required',
            'description'  => 'required',
        ], $messages = [
            'required' => 'The :attribute field is required.',
            'max'      => 'The :attribute number should be max 15 digits.',
        ]);
        if ($validator->fails()) {
            return response()->json(['result' => 0, 'errors' => $validator->errors()]);
            return false;
        }
        $result = $this->front_model->addDistributor($RequestData);
        if ($result) {
            return response()->json(['result' => 1, 'url' => route('front/becomedistributor'), 'msg' => 'Submitted as Distributor successfully.']);
        } else {
            return response()->json(['result' => -1, 'msg' => 'Something went wrong.']);
        }
    }

    // ------------------------------------------------- Shop --------------------------------------------------------
    public function shop(Request $Request)
    {
        $data['title'] = 'Shop';
        $data['brands'] = $this->front_model->getAllBrands()->where('region', $this->session_region);
        $data['products'] = $this->product_model->getAllProducts()->where('region', $this->session_region);
        $data['toprated_product'] = $this->front_model->getProducts()->where('region', $this->session_region);
        $data['banners'] = $this->front_model->getBanners('shop')->where('region', $this->session_region);
        if ($data['toprated_product']->isNotEmpty()) {
            foreach ($data['toprated_product'] as $row) {
                $reviewdata  = $this->front_model->getReviewByProductId($row->product_id)->where('region', $this->session_region);
                $avgrating = $reviewdata->avg('rating');
                $totalreview = $reviewdata->count('review_id');
                $row->rating = !empty($avgrating) ? $avgrating : 0;
                $row->totalreview = !empty($totalreview) ? $totalreview : 0;
            }
            $data['toprated_product'] = $data['toprated_product']->where('region', $this->session_region)->sortByDesc('rating');
        }
        $data['featured_product'] = $data['toprated_product']->where('is_featured', 'Yes')->where('region', $this->session_region);
        return $this->loadview('shop/shop', $data);
    }

    public function productList(Request $Request){
        $data['title'] = 'Product List';
        $data['keywords'] = $Request->get('searchProduct');
        $data['brand_id'] = explode(':', $Request->get('brandsFilter'));
        $data['category_id'] = explode(':', $Request->get('categoryFilter'));
        $data['application_id'] = explode(':', $Request->get('applicationFilter'));
        $data['material_id'] = explode(':', $Request->get('materialFilter'));
        $product_id = decryptionID($Request->post('product_id'));
        if(!empty($data['brand_id'][0])){
            $data['filter_categories'] = $this->category_model->getAllCategoriesByBrand($data['brand_id'])->where('region', $this->session_region);
        }else{
            $data['filter_categories'] = $this->category_model->getAllCategories(NULL)->where('region', $this->session_region);
        }
        $data['applications'] = $this->product_model->getAllApplications();
        $data['materials'] = $this->product_model->getAllMaterials();
        if(!empty($data['application_id'][0])){
            $data['product_by_application'] = $this->front_model->getProductByApplication($data['application_id']);
        }
        if(!empty($data['material_id'][0])){
            $data['product_by_material'] = $this->front_model->getProductByMaterial($data['material_id']);
        }
        if(!empty($data['product_by_application']) && !empty($data['product_by_material'])){
            $applicationAndMaterial = $data['product_by_application']->merge($data['product_by_material']);
        }else if(!empty($data['product_by_application'])){
            $applicationAndMaterial = $data['product_by_application'];
        }else if(!empty($data['product_by_material'])){
            $applicationAndMaterial = $data['product_by_material'];
        }
        $product_filter_id = [];
        if(!empty($applicationAndMaterial)){
            $i = 0;
            foreach($applicationAndMaterial as $value){
                $product_filter_id[$i] = $value->product_id;
                $i++;
            }
            $brand_filter_id = $this->front_model->getBrandIdByProductId($product_filter_id);
        }
        if(empty($data['keywords']) && empty($data['brand_id']) && empty($data['category_id']) && empty($data['application_id']) && empty($data['material_id'])){
            $data['products'] = $this->product_model->getAllProducts()->where('region', $this->session_region);
        }else if(!empty($applicationAndMaterial)){
            $data['products'] = $this->front_model->getProductByApplicationAndMaterial($product_filter_id)->where('region', $this->session_region);
        }else{
            $data['products'] = $this->front_model->getProductByKeyWordsBrandCategory($data['keywords'], $data['brand_id'], $data['category_id'])->where('region', $this->session_region);
        }
        // dd($data['products']);
        $data['productcount'] = count($data['products']);
        $data['user_detail'] = $this->user_detail();
        $allcartdata = $this->getCartItem();
        if ($data['products']->isNotEmpty()) {
            foreach ($data['products'] as $row) {
                $row->cartqty = $this->front_model->getCartByUserId($row->product_id, @$data['user_detail']->user_id);
                if (empty($row->cartqty)) {
                    if (!empty($allcartdata)) {
                        foreach ($allcartdata as $col[0]) {
                            if ($col[0][0]->product_id == $row->product_id) {
                                $row->cartqty = $col[0][0];
                            }
                        }
                    }
                }
            }
        }
        $rating = $this->front_model->getReviewByProductId($product_id)->avg('rating');
        $data['rating'] = 0;
        foreach ($data['products'] as $row) {
            $reviewdata  = $this->front_model->getReviewByProductId($row->product_id);
            $avgrating = $reviewdata->avg('rating');
            $totalreview = $reviewdata->count('review_id');
            $row->rating = !empty($avgrating) ? $avgrating : 0;
            $row->totalreview = !empty($totalreview) ? $totalreview : 0;
        }
        return $this->loadview('product/product_list', $data);
    }

    // public function productSearchWrapper(Request $Request)
    // {
    //     $keywords = $Request->post('keyword');
    //     $product_id = $Request->post('product_id');
    //     $data['brands'] = $this->front_model->getAllBrands()->where('region', $this->session_region);
    //     $data['products'] = $this->front_model->getProductByKeyWords($keywords)->where('region', $this->session_region);
    //     $data['productcount'] = count($data['products']);
    //     $data['user_detail'] = $this->user_detail();

    //     if ($data['products']->isNotEmpty()) {
    //         foreach ($data['products'] as $row) {
    //             $row->cartqty = $this->front_model->getCartByUserId($row->product_id, @$data['user_detail']->user_id);
    //         }
    //     }
    //     $rating = $this->front_model->getReviewByProductId($product_id)->avg('rating');
    //     $data['rating'] = 0;
    //     foreach ($data['products'] as $row) {
    //         $reviewdata  = $this->front_model->getReviewByProductId($row->product_id)->where('region', $this->session_region);
    //         $avgrating = $reviewdata->avg('rating');
    //         $totalreview = $reviewdata->count('review_id');
    //         $row->rating = !empty($avgrating) ? $avgrating : 0;
    //         $row->totalreview = !empty($totalreview) ? $totalreview : 0;
    //     }
    //     $htmlwrapper = view('front/product/productwrapper/product-search-wrapper', $data)->render();
    //     return response()->json(['result' => 1, 'msg' => 'Product found...', 'htmlwrapper' => $htmlwrapper]);
    // }

    public function shopProductSearchWrapper(Request $Request){
        $keywords = $Request->post('keyword');
        $data['products'] = $this->front_model->getProductByKeyWords($keywords)->where('region', $this->session_region);
        $htmlwrapper = view('front/shop/shopwrapper/shop-product-search-wrapper', $data)->render();
        return response()->json(['result' => 1, 'msg' => 'Product found...', 'htmlwrapper' => $htmlwrapper]);
    }

    public function shopProductDetail($product_id, Request $Request)
    {
        $data['title'] = 'Shop Product Detail';
        $product_id = decryptionID($product_id);
        $data['products'] = $this->product_model->getAllProducts()->where('region', $this->session_region);
        $data['productcount'] = count($data['products']);
        $data['product'] = $this->product_model->get_product_by_id($product_id);
        $data['product_images'] = $this->product_model->get_product_image_by_id($product_id);
        $dbcart = $this->front_model->getCartByUserId($product_id, @$data['user_detail']->user_id);
        if (empty($dbcart)) {
            $allcartdata = $this->getCartItem();
            if (!empty($allcartdata)) {
                foreach ($allcartdata as $row) {
                    if ($row[0]->product_id == $product_id) {
                        $dbcart = $row;
                    }
                }
            }
        }
        $data['cartdata'] = @$dbcart[0];
        $data['productfeatures'] = $this->product_model->getAllProductFeature($product_id);
        $data['productspecification'] = $this->product_model->getAllProductSpecification($product_id);
        $rating = $this->front_model->getReviewByProductId($product_id)->avg('rating');
        $data['rating'] = 0;
        foreach ($data['products'] as $row) {
            $reviewdata = $this->front_model->getReviewByProductId($product_id);
            $avgrating = $reviewdata->avg('rating');
            $totalreview = $reviewdata->count('review_id');
            $row->rating = !empty($avgrating) ? $avgrating : 0;
            $row->totalreview = !empty($totalreview) ? $totalreview : 0;
        }
        $data['productreviews'] = $this->front_model->getReviewByProductId($product_id);
        // ----------------------------------- Ratings Calculation -------------------------
        $rating = [
            1 => [],
            2 => [],
            3 => [],
            4 => [],
            5 => []
        ];
        if ($data['productreviews']->isNotEmpty()) {
            foreach ($data['productreviews'] as $key => $val) {
                if ($val->rating == 1) {
                    array_push($rating[1], $val);
                }
                if ($val->rating == 2) {
                    array_push($rating[2], $val);
                }
                if ($val->rating == 3) {
                    array_push($rating[3], $val);
                }
                if ($val->rating == 4) {
                    array_push($rating[4], $val);
                }
                if ($val->rating == 5) {
                    array_push($rating[5], $val);
                }
            }
        }
        $data['rating1'] = count($rating[1]);
        $data['rating2'] = count($rating[2]);
        $data['rating3'] = count($rating[3]);
        $data['rating4'] = count($rating[4]);
        $data['rating5'] = count($rating[5]);
        // ----------------------------------- End Ratings Calculation -------------------------
        $data['customerreviews'] = $this->front_model->getReviewByProductId($product_id)->take(3);
        return $this->loadview('shop/shop_product_detail', $data);
    }

    public function shopProductDetailWrapper(Request $Request)
    {
        $data['title'] = 'Shop Product Detail';
        $product_id = decryptionID($Request->post('product_id'));
        $data['products'] = $this->product_model->getAllProducts()->where('region', $this->session_region);
        $data['productcount'] = count($data['products']);
        $data['currency'] = $this->front_model->getCurrency($this->session_region);
        $data['product'] = $this->product_model->get_product_by_id($product_id);
        $data['product_images'] = $this->product_model->get_product_image_by_id($product_id);
        $dbcart = $this->front_model->getCartByUserId($product_id, @$data['user_detail']->user_id);
        if (empty($dbcart)) {
            $allcartdata = $this->getCartItem();
            if (!empty($allcartdata)) {
                foreach ($allcartdata as $row) {
                    if ($row[0]->product_id == $product_id) {
                        $dbcart = $row;
                    }
                }
            }
        }
        $data['cartdata'] = @$dbcart[0];
        $data['productfeatures'] = $this->product_model->getAllProductFeature($product_id);
        $data['productspecification'] = $this->product_model->getAllProductSpecification($product_id);
        $rating = $this->front_model->getReviewByProductId($product_id)->avg('rating');
        $data['rating'] = 0;
        foreach ($data['products'] as $row) {
            $reviewdata = $this->front_model->getReviewByProductId($product_id);
            $avgrating = $reviewdata->avg('rating');
            $totalreview = $reviewdata->count('review_id');
            $row->rating = !empty($avgrating) ? $avgrating : 0;
            $row->totalreview = !empty($totalreview) ? $totalreview : 0;
        }
        $data['productreviews'] = $this->front_model->getReviewByProductId($product_id);
        // ----------------------------------- Ratings Calculation -------------------------
        $rating = [
            1 => [],
            2 => [],
            3 => [],
            4 => [],
            5 => []
        ];
        if ($data['productreviews']->isNotEmpty()) {
            foreach ($data['productreviews'] as $key => $val) {
                if ($val->rating == 1) {
                    array_push($rating[1], $val);
                }
                if ($val->rating == 2) {
                    array_push($rating[2], $val);
                }
                if ($val->rating == 3) {
                    array_push($rating[3], $val);
                }
                if ($val->rating == 4) {
                    array_push($rating[4], $val);
                }
                if ($val->rating == 5) {
                    array_push($rating[5], $val);
                }
            }
        }
        $data['rating1'] = count($rating[1]);
        $data['rating2'] = count($rating[2]);
        $data['rating3'] = count($rating[3]);
        $data['rating4'] = count($rating[4]);
        $data['rating5'] = count($rating[5]);
        // ----------------------------------- End Ratings Calculation -------------------------
        $data['customerreviews'] = $this->front_model->getReviewByProductId($product_id)->take(3);
        $htmlwrapper = view('front/shop/shopwrapper/shop-product-details-wrapper', $data)->render();
        return response()->json(['result' => 1, 'htmlwrapper' => $htmlwrapper]);
    }

    public function cartWrapper(Request $Request)
    {
        $product_id = decryptionID($Request->post('product_id'));
        $user_id = $Request->session()->get('mat_user_id');
        $cart_id = $Request->post('cart_id');
        $qty = $Request->post('qty');
        if (empty($user_id)) {
            $this->updateCart($product_id, $qty, $Request);
            $result = 1;
        } else {
            $product_data = $this->front_model->getProductsByProductId($product_id);
            if (!empty($product_data)) {
                if ($qty > $product_data->product_quantity) {
                    return response()->json(['result' => -1, 'msg' => 'Out Of Stock Available  Qty is -' . $product_data->product_quantity, 'url' => '']);
                }
            }
            if (empty($cart_id)) {
                $updateQuantity = $this->front_model->insertCartQuantity($product_id, $user_id, $qty);
            } else {
                $updateQuantity = $this->front_model->updateCartQuantity($cart_id, $qty);
            }
        }
        $qty = $Request->post('qty');
        $data['product_features'] = $this->product_model->getAllProductFeature($product_id);
        $similar_product_id = [];
        $data['cartdata'] = $this->getCartItem();
        $data['currency'] = $this->front_model->getCurrency($this->session_region);
        $data['productcount'] = !empty($data['cartdata']) ? count($data['cartdata']) : 0;
        $product_price = 0;
        $rating = $this->front_model->getReviewByProductId($product_id)->avg('rating');
        $data['rating'] = 0;
        if (!empty($data['cartdata'])) {
            // dd($data['cartdata']);
            foreach ($data['cartdata'] as $row) {
                $reviewdata = $this->front_model->getReviewByProductId(@$row[0]->product_id);
                $data['product_features'] = $this->product_model->getAllProductFeature($row[0]->product_id);
                $avgrating = $reviewdata->avg('rating');
                $totalreview = $reviewdata->count('review_id');
                $row[0]->rating = !empty($avgrating) ? $avgrating : 0;
                $row[0]->totalreview = !empty($totalreview) ? $totalreview : 0;
                $price = $qty * @$row[0]->product_price;
                $product_price += $price;
            }
        }
        $data['cart_price'] = $product_price;
        $data['shipping_charges'] = 0;
        $data['tax'] = 0;
        $data['total_price'] = $data['cart_price'] + $data['shipping_charges'] + $data['tax'];
        // -------------------- Similar Products ----------------------------
        $data['similar_products'] = $this->front_model->getSimilarProducts($similar_product_id)->where('region', $this->session_region);
        $htmlwrapper = view('front/shop/cartwrapper', $data)->render();
        return response()->json(['result' => 1, 'msg' => 'Item Update...', 'htmlwrapper' => $htmlwrapper]);
    }

    // -------------------------------- Request Quote -----------------------------------
    public function requestQuote(Request $Request)
    {
        $data['title'] = 'Request A Quote';
        $data['brands'] = $this->front_model->getAllBrands()->where('region', $this->session_region);
        $data['country_codes'] = $this->front_model->getphonecode();
        return $this->loadview('request_quote', $data);
    }

    public function categoryRequestWrapper(Request $request)
    {
        $brand_ids = $request->post('selectedbrands');
        $data['category'] = $this->front_model->getCategoryByBrandIDS($brand_ids)->where('region', $this->session_region);
        $htmlwrapper = view('front/requestaqoate/category-wrapper', $data)->render();
        return response()->json(['result' => 1, 'htmlwrapper' => $htmlwrapper]);
    }

    public function subcategoryRequestWrapper(Request $request)
    {
        $priviouspagedata = $request->post('data');
        $data['subcategory'] = $this->front_model->getSubcategoryByBrandidAndCategoryId($priviouspagedata)->where('region', $this->session_region)->where('status', 'Active');
        $htmlwrapper = view('front/requestaqoate/sub-category-wrapper', $data)->render();
        return response()->json(['result' => 1, 'htmlwrapper' => $htmlwrapper]);
    }

    public function productRequestWrapper(Request $request)
    {
        $priviouspagedata = $request->post('data');
        $data['products'] = $this->front_model->getProductsRequestaquote($priviouspagedata)->where('region', $this->session_region);
        $htmlwrapper = view('front/requestaqoate/product-wrapper', $data)->render();
        return response()->json(['result' => 1, 'htmlwrapper' => $htmlwrapper]);
    }

    public function productSpecificationWrapper(Request $request)
    {
        $priviouspagedata = $request->post('data');
        $data['products'] = $this->front_model->getProdutsByProductsIds($priviouspagedata)->where('region', $this->session_region);
        if ($data['products']->isNotEmpty()) {
            foreach ($data['products'] as $row) {
                $row->productspecification = $this->front_model->getProductDimentions($row->product_id)->where('region', $this->session_region);
            }
        }
        $htmlwrapper = view('front/requestaqoate/specification-wrapper', $data)->render();
        return response()->json(['result' => 1, 'htmlwrapper' => $htmlwrapper]);
    }


    public function personalRequestWrapper(Request $request)
    {
        $data['title'] = 'Personal';
        $data['country_codes'] = $this->front_model->getphonecode();
        $product_id = $request->post('product_id');
        $i = 0;
        foreach ($product_id as $pro_id) {
            $j = 0;
            $product_attribute = $this->front_model->getProductDimentions($pro_id)->where('region', $this->session_region);
            foreach ($product_attribute as $attribute) {
                $attribute_value = $request->post('specificationvalue_' . $attribute->id);
                $array[$i][$j] = array(
                    'product_id'        => $pro_id,
                    'product_name'      => $attribute->product_name,
                    'attribute_id'      => $attribute->id,
                    'attribute_name'    => $attribute->attribute_name,
                    'attribute_value'   => $attribute_value,
                );
                $j++;
            }
            $i++;
        }
        $finalarray = [];
        foreach ($array as $row) {
            $temp2 = [];
            foreach ($row as $col) {
                $temp1 = [];
                foreach ($col['attribute_value'] as $key => $value) {
                    $temp['product_id'] = $col['product_id'];
                    $temp['product_name'] = $col['product_name'];
                    $temp['attribute_name'] = $col['attribute_name'];
                    $temp['attribute_value'] = $value;
                    if (!isset($temp2[$col['product_id']])) {
                        $temp1[][] = $temp;
                    } else {
                        $temp1[] = $temp;
                    }
                }
                if (!isset($temp2[$col['product_id']])) {
                    $temp2[$col['product_id']] = $temp1;
                } else {
                    for ($i = 0; $i < count($temp2[$col['product_id']]); $i++) {
                        array_push($temp2[$col['product_id']][$i], $temp1[$i]);
                    }
                }
            }
            $finalarray[] = $temp2;
        }

        session()->put('previous_data', $finalarray);
        $htmlwrapper = view('front/requestaqoate/personal-wrapper', $data)->render();
        return response()->json(['result' => 1, 'htmlwrapper' => $htmlwrapper]);
    }

    public function doAddPersonal(Request $request)
    {
        $RequestData = $request->all();
        $validator = Validator::make($RequestData, $rules = [
            'name'         => 'required|regex:/^[\pL\s\-]+$/u',
            'email'        => 'required',
            'company_name' => 'required|regex:/^[\pL\s\-]+$/u',
            'phone_number'   => 'required|max:15',
            'description'  => 'required',
        ], $messages = [
            'required' => 'The :attribute field is required.',
            'max'      => 'The :attribute should be max 15 digits.',
        ]);
        if ($validator->fails()) {
            return response()->json(['result' => 0, 'errors' => $validator->errors()]);
            return false;
        }
        Session()->put('personal_data', $RequestData);
        $data['specification_data'] = session()->get('previous_data');
        $lead_id = session()->get('lead_id');
        if (empty($lead_id)) {
            $result = $this->front_model->addPersonalDetails($RequestData);
        } else {
            $result = null;
        }
        session()->put('lead_id', $result);
        $htmlwrapper = view('front/requestaqoate/summary-wrapper', $data)->render();
        return response()->json(['result' => 1, 'htmlwrapper' => $htmlwrapper]);
    }

    public function finalRequestqoate(Request $request)
    {
        $lead_id = session()->get('lead_id');
        $final_array = [];
        $alldata = session()->get('previous_data');
        foreach ($alldata as $row) {
            foreach ($row as $col) {
                foreach ($col as $last) {
                    foreach ($last as $l) {
                        $l['lead_id'] = $lead_id;
                        @array_push($final_array, $l);
                    }
                }
            }
        }
        $result = $this->front_model->insertRequestQuoteDetails($final_array);
        if ($result) {
            session()->forget('lead_id');
            return response()->json(['result' => 1, 'msg' => 'Quote form submit successfully', 'url' => url('/')]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'Something Went Wrong']);
        }
    }

    // --------------------- Contact Us -------------------------------------
    public function contactUs()
    {
        $data['title'] = 'Contact Us';
        $data['country_codes'] = $this->front_model->getphonecode();
        return $this->loadview('contact_us', $data);
    }

    public function doContactUs(Request $Request)
    {
        $RequestData = $Request->all();
        $validator = Validator::make($RequestData, $rules = [
            'name'         => 'required|regex:/^[\pL\s\-]+$/u',
            'email'        => 'required',
            'company_name' => 'required|regex:/^[\pL\s\-]+$/u',
            'contact_no'   => 'required|max:15',
            'address'      => 'required',
            'description'  => 'required',
        ], $messages = [
            'required' => 'The :attribute field is required.',
            'max'                 => 'The :attribute number should be max 15 digits.',
        ]);
        if ($validator->fails()) {
            return response()->json(['result' => 0, 'errors' => $validator->errors()]);
            return false;
        }
        $result = $this->front_model->doContactUs($RequestData);
        if ($result) {
            return response()->json(['result' => 1, 'url' => route('front/contactus'), 'msg' => 'Your query submitted successfully.']);
        } else {
            return response()->json(['result' => -1, 'msg' => 'Something went wrong.']);
        }
    }

    // --------------------------------------- Product ------------------------------------
    public function product($brand_id){
        $data['title'] = 'Product';
        $brand_id = decryptionID($brand_id);
        $data['brands'] = $this->front_model->getAllGroupByBrands()->where('region', $this->session_region);
        $data['brand_name'] = $this->brand_model->get_brand_by_id($brand_id);
        $data['brand'] = $data['brands']->first();
        if (!empty($data['brand'])) {
            $data['brand_id'] = $data['brand']->brand_id;
        } else {
            $data['brand_id'] = 0;
        }
        $data['categories_product'] = $this->category_model->getAllCategories($brand_id)->where('region', $this->session_region);
        return $this->loadview('product/product', $data);
    }

    public function productWrapper(Request $Request){
        $data['title'] = 'Product';
        $data['brands'] = $this->front_model->getAllGroupByBrands()->where('region', $this->session_region);
        $brand_id = $Request->post('brand_id');
        $data['brand'] = $this->brand_model->get_brand_by_id($brand_id);
        if (!empty($brand_id)) {
            $data['brand_id'] = $brand_id;
        } else {
            $data['brand_id'] = 0;
        }
        $data['categories'] = $this->category_model->getAllCategories($brand_id)->where('region', $this->session_region);
        $htmlwrapper = view('front/product/productwrapper/product-wrapper', $data)->render();
        return response()->json(['result' => 1, 'htmlwrapper' => $htmlwrapper]);
    }

    public function productSubCategory($brand_id, $category_id){
        $data['title'] = 'Product Sub-Category';
        $brand_id = decryptionID($brand_id);
        $category_id = decryptionID($category_id);
        $data['category'] = $this->category_model->getCategoriesById($category_id);
        $data['brand'] = $this->brand_model->get_brand_by_id($brand_id);
        $data['brands'] = $this->front_model->getAllBrands()->where('region', $this->session_region);
        $data['categories_product'] = $this->category_model->getAllCategories($brand_id)->where('region', $this->session_region);
        $data['sub_categories'] = $this->category_model->getAllSubCategoriesByCategoryId($brand_id, $category_id)->where('region', $this->session_region);
        return $this->loadview('product/product_sub_category', $data);
    }

    public function subCategoryWrapper(Request $Request)
    {
        $data['title'] = 'Product Sub-Category';
        $brand_id = $Request->post('brand_id');
        $category_id = $Request->post('category_id');
        $data['brands'] = $this->front_model->getAllBrands()->where('region', $this->session_region);
        $data['categories'] = $this->category_model->getAllCategories($brand_id);
        $data['brand'] = $this->brand_model->get_brand_by_id($brand_id);
        $data['category'] = $this->category_model->getCategoriesById($category_id);
        $data['sub_categories'] = $this->category_model->getAllSubCategoriesByCategoryId($brand_id, $category_id)->where('region', $this->session_region);
        $htmlwrapper = view('front/product/productwrapper/sub-category-wrapper', $data)->render();
        return response()->json(['result' => 1, 'htmlwrapper' => $htmlwrapper]);
    }

    public function productListing($brand_id = null, $category_id = null, $sub_category_id = null)
    {
        $data['title'] = 'Product Listing';
        $brand_id = decryptionID($brand_id);
        $category_id = decryptionID($category_id);
        $sub_category_id = decryptionID($sub_category_id);
        $data['brand'] = $this->brand_model->get_brand_by_id($brand_id);
        $data['category'] = $this->category_model->getCategoriesById($category_id);
        $data['sub_category'] = $this->category_model->getSubCategoriesById($sub_category_id);
        $data['brands'] = $this->front_model->getAllBrands()->where('region', $this->session_region);
        $data['categories'] = $this->category_model->getAllCategories($brand_id)->where('region', $this->session_region);
        $data['sub_categories'] = $this->category_model->getAllSubCategoriesByCategoryId($brand_id, $category_id)->where('region', $this->session_region);
        $data['products'] = $this->product_model->getProductBySubCategoryId($brand_id, $category_id, $sub_category_id)->where('region', $this->session_region);
        $data['user_detail'] = $this->user_detail();
        return $this->loadview('product/product_listing', $data);
    }

    public function productListingWrapper(Request $Request)
    {
        $data['title'] = 'Product Listing';
        $brand_id = $Request->post('brand_id');
        $category_id = $Request->post('category_id');
        $sub_category_id = $Request->post('sub_category_id');
        $data['brands'] = $this->front_model->getAllBrands()->where('region', $this->session_region);
        $data['categories'] = $this->category_model->getAllCategories($brand_id)->where('region', $this->session_region);
        $data['sub_categories'] = $this->category_model->getAllSubCategoriesByCategoryId($brand_id, $category_id)->where('region', $this->session_region);
        $data['brand'] = $this->brand_model->get_brand_by_id($brand_id);
        $data['category'] = $this->category_model->getCategoriesById($category_id);
        $data['sub_category'] = $this->category_model->getSubCategoriesById($sub_category_id);
        $data['products'] = $this->product_model->getProductBySubCategoryId($brand_id, $category_id, $sub_category_id)->where('region', $this->session_region);
        $htmlwrapper = view('front/product/productwrapper/product-listing-wrapper', $data)->render();
        return response()->json(['result' => 1, 'htmlwrapper' => $htmlwrapper]);
    }

    public function productDetails($brand_id, $category_id, $sub_category_id, $product_id)
    {
        $data['title'] = 'Product Details';
        $brand_id = decryptionID($brand_id);
        $category_id = decryptionID($category_id);
        $sub_category_id = decryptionID($sub_category_id);
        $product_id = decryptionID($product_id);
        $data['product_id'] = $product_id;
        $data['brands'] = $this->front_model->getAllBrands()->where('region', $this->session_region);
        $data['brand'] = $this->brand_model->get_brand_by_id($brand_id);
        $data['category'] = $this->category_model->getCategoriesById($category_id);
        $data['sub_category'] = $this->category_model->getSubCategoriesById($sub_category_id);
        $data['products'] = $this->product_model->get_product_by_id($product_id);
        $data['product_images'] = $this->product_model->get_product_image_by_id($product_id);
        $data['product'] = $this->product_model->getProductBySubCategoryId($brand_id, $category_id, $sub_category_id)->where('region', $this->session_region);
        $data['categories'] = $this->category_model->getAllCategories($brand_id)->where('region', $this->session_region);
        $data['sub_categories'] = $this->category_model->getAllSubCategoriesByCategoryId($brand_id, $category_id)->where('region', $this->session_region);
        $data['product_features'] = $this->product_model->getAllProductFeature($product_id);
        $data['product_specification'] = $this->product_model->getAllProductSpecification($product_id);
        $data['product_dimension'] = $this->product_model->getAllProductDimDetails($product_id);
        $rating = $this->front_model->getReviewByProductId($product_id)->avg('rating');
        $data['rating'] = 0;
        return $this->loadview('product/product_details', $data);
    }

    public function productDetailsWrapper(Request $Request)
    {
        $data['title'] = 'Product Details';
        $brand_id = $Request->post('brand_id');
        $category_id = $Request->post('category_id');
        $sub_category_id = $Request->post('sub_category_id');
        $product_id = $Request->post('product_id');
        $data['product_id'] = $product_id;
        $data['brands'] = $this->front_model->getAllBrands()->where('region', $this->session_region);
        $data['brand'] = $this->brand_model->get_brand_by_id($brand_id);
        $data['category'] = $this->category_model->getCategoriesById($category_id);
        $data['sub_category'] = $this->category_model->getSubCategoriesById($sub_category_id);
        $data['products'] = $this->product_model->get_product_by_id($product_id);
        $data['product_images'] = $this->product_model->get_product_image_by_id($product_id);
        $data['product'] = $this->product_model->getProductBySubCategoryId($brand_id, $category_id, $sub_category_id)->where('region', $this->session_region);
        $data['categories'] = $this->category_model->getAllCategories($brand_id)->where('region', $this->session_region);
        $data['sub_categories'] = $this->category_model->getAllSubCategoriesByCategoryId($brand_id, $category_id)->where('region', $this->session_region);
        $data['product_features'] = $this->product_model->getAllProductFeature($product_id);
        $data['product_specification'] = $this->product_model->getAllProductSpecification($product_id);
        // $data['product_dimension'] = $this->product_model->getAllProductDimensions($product_id);
        $data['product_dimension'] = $this->product_model->getAllProductDimDetails($product_id);
        $rating = $this->front_model->getReviewByProductId($product_id)->avg('rating');
        $data['rating'] = 0;
        $htmlwrapper = view('front/product/productwrapper/product-details-wrapper', $data)->render();
        return response()->json(['result' => 1, 'htmlwrapper' => $htmlwrapper]);
    }

    public function submitReview(Request $Request, $pid)
    {
        $RequestData = $Request->all();
        $user_id = $Request->session()->get('mat_user_id');
        $product_id = decryptionID($pid);
        $validator = Validator::make($RequestData, $rules = [
            'name'        => 'required',
            'email'       => 'required',
            'message'     => 'required',
        ], $messages = [
            'required' => 'The :attribute field is required.',
        ]);
        if ($validator->fails()) {
            return response()->json(['result' => 0, 'errors' => $validator->errors()]);
            return false;
        }
        $is_login = $this->isLogin();
        if($is_login){
            $result = $this->front_model->submitReview($RequestData, $product_id, $user_id);
            if ($result) {
                return response()->json(['result' => 1, 'url' => url(''), 'msg' => 'Review submitted successfully.']);
            } else {
                return response()->json(['result' => -1, 'msg' => 'Something went wrong!!!']);
            }
        }else{
            return response()->json(['result' => -1, 'msg' => 'Please login for submit review!!!']);
        }
    }

    // ---------------------- Profile Settings -----------------------------------------
    public function profileSettings()
    {
        $data['title'] = 'Profile Settings';
        $data['user_detail'] = $this->user_detail();
        $data['country_codes'] = $this->front_model->getphonecode();
        return $this->loadview('user/profile_setting', $data);
    }

    public function editProfileSettings(Request $Request)
    {
        $RequestData = $Request->all();
        $user_id = $Request->session()->get('mat_user_id');
        $validator = Validator::make($RequestData, $rules = [
            'name'        => 'required',
            'mobile'      => 'required|max:15',
        ], $messages = [
            'required' => 'The :attribute field is required.',
            'max' => 'The :attribute number should be max 15 digits.'
        ]);
        if ($validator->fails()) {
            return response()->json(['result' => 0, 'errors' => $validator->errors()]);
            return false;
        }
        $result = $this->front_model->editProfileSettings($RequestData, $user_id);
        if ($result) {
            return response()->json(['result' => 1, 'url' => route('front/profilesettings'), 'msg' => 'Profile updated successfully.']);
        } else {
            return response()->json(['result' => -1, 'msg' => 'No changes were found.']);
        }
    }

    // ---------------------- Address Settings -----------------------------------------
    public function addressSettings(Request $request)
    {
        $data['title'] = 'Address Settings';
        $user_id = $request->session()->get('mat_user_id');
        $data['user_detail'] = $this->user_detail();
        $data['user_address_detail'] = $this->front_model->getUserAddressDetails($user_id)->where('region', $this->session_region);
        return $this->loadview('user/address_settings', $data);
    }

    public function addAddress(Request $request)
    {
        $data['title'] = 'Add Address';
        $user_id = $request->session()->get('mat_user_id');
        $data['country_codes'] = $this->front_model->getphonecode();
        $data['user_detail'] = $this->user_detail();
        $data['user_address_detail'] = $this->front_model->getUserAddressDetails($user_id)->where('region', $this->session_region);
        foreach ($data['user_address_detail'] as $row) {
            $data['countries'] = $this->front_model->getAllCountries();
            $data['states'] = $this->front_model->getStatesByCountryId($row->country);
            $data['cities'] = $this->front_model->getCitiesByStateId($row->state);
        }
        $data['countries'] = $this->front_model->getAllCountries();
        return $this->loadview('user/add_address', $data);
    }

    public function getStatesByCountryId(Request $request)
    {
        $country = $request->post('country');
        $data['states'] = $this->front_model->getStatesByCountryId($country);
        $html = view('front/wrapper/state_wrapper', $data)->render();
        return response()->json(['result' => 1, 'html' => $html]);
    }

    public function getCitiesByStateId(Request $request)
    {
        $state = $request->post('state');
        $data['cities'] = $this->front_model->getCitiesByStateId($state);
        $html = view('front/wrapper/city_wrapper', $data)->render();
        return response()->json(['result' => 1, 'html' => $html]);
    }

    public function doAddAddress(Request $Request){
        $RequestData = $Request->all();
        $user_id = $Request->session()->get('mat_user_id');
        $validator = Validator::make($RequestData, $rules = [
            'name'         => 'required|regex:/^[\pL\s\-]+$/u',
            'mobile'       => 'required|max:15',
            'address'      => 'required',
            'landmark'     => 'required',
            'country_id'   => 'required',
            'state_id'     => 'required',
            'city_id'      => 'required',
            'postal_code'  => 'required',
        ], $messages = [
            'required'            => 'The :attribute field is required.',
            'max'                 => 'The :attribute number should be max 15 digits.',
            'country_id.required' => 'Please Select Country ',
            'state_id.required'   => 'Please Select State ',
            'city_id.required'    => 'Please Select City ',
        ]);
        if ($validator->fails()) {
            return response()->json(['result' => 0, 'errors' => $validator->errors()]);
            return false;
        }
        $result = $this->front_model->doAddAddress($RequestData, $user_id);
        if ($result) {
            return response()->json(['result' => 1, 'url' => route('front/addresssettings'), 'msg' => 'Address added successfully.']);
        } else {
            return response()->json(['result' => -1, 'msg' => 'Something went wrong.']);
        }
    }

    public function editAddress(Request $request)
    {
        $data['title'] = 'Edit Address';
        return $this->loadview('user/edit_address', $data);
    }

    public function selectAddress(Request $request)
    {
        $data['title'] = 'Edit Address';
        return $this->loadview('user/select_address', $data);
    }
    // ---------------------------------------------------------------------------------

    // ----------------------------- Orders --------------------------------------------
    public function myOrders()
    {
        $data['title'] = 'My Orders';
        return $this->loadview('order/my_orders', $data);
    }

    public function orderSuccessful()
    {
        $data['title'] = "Order Successful";
        return $this->loadview('order/order_successful', $data);
    }

    public function trackPackage()
    {
        $data['title'] = "Track Package";
        return $this->loadview('order/track_package', $data);
    }

    public function cancleOrder()
    {
        $data['title'] = "Cancle Order";
        return $this->loadview('order/cancle_order', $data);
    }
    // ----------------------------------------------------------------------------------

    // ---------------------------------- Services --------------------------------------
    public function serviceDetails()
    {
        $data['title'] = 'Service Details';
        return $this->loadview('services/service_details', $data);
    }

    // ----------------------------- Payment ---------------------------------------------
    public function paymentMethod()
    {
        $data['title'] = "Payment Method";
        return $this->loadview('payment/payment_method', $data);
    }
    // -----------------------------------------------------------------------------------

    //---------------------------------------------------Forgot Password---------------------------------------------------------
    public function forgotPassword()
    {
        $data['title'] = "Forgot Password";
        return $this->loadview('user/forgot_password', $data);
    }

    public function doForgotPassword(Request $request)
    {
        $RequestData = $request->all();
        $encrypted_id = $request->post('user_id');
        $user_id = decryptionID($encrypted_id);
        $validator = Validator::make($RequestData, $rules = [
            'email'        => 'required',
        ], $messages = [
            'required' => 'The :attribute field is required.',
        ]);
        if ($validator->fails()) {
            return response()->json(['result' => 0, 'errors' => $validator->errors()]);
            return false;
        }
        $email = $request->post('email');
        $user_detail = $this->front_model->getUserByEmail($email);
        if (!empty($user_detail)) {
            $otp = mt_rand(1111, 9999);
            $mail['subject'] = 'Otp For Forgot Password!';
            $mail['message'] = 'Your Otp Is ' . $otp;
            $mail['email'] = $user_detail->email;
            $mail['user_id'] = $user_detail->user_id;
            $mail['name'] = $user_detail->name;
            $mail['otp'] = $otp;
            $user_id = $user_detail->user_id;
            $result = $this->sendPasswordResetMail($mail);
            $result = $this->front_model->sendOtp($otp, $user_id);
            if ($result) {
                session()->put('forgot_email', $email);
                session()->put('resend_email', $email);
                update('users', 'user_id', $user_id, ['forgot_password_status' => '0']);
                return response()->json(['result' => 1, 'msg' => 'OTP sent on your mail', 'url' => route('front/passwordOtp', ['user_id' => encryptionId($user_id)])]);
            } else {
                return response()->json(['result' => -1, 'msg' => 'Something went wrong!!!']);
            }
        } else {
            return response()->json(['result' => -1, 'msg' => 'User does not exist.', 'data' => null]);
        }
    }

    public function resendOTP(Request $request)
    {
        $email = $request->session()->get('resend_email');
        $user_detail = $this->front_model->getUserByEmail($email);
        $encrypted_id = $user_detail->user_id;
        $user_id = decryptionID($encrypted_id);
        update('users', 'user_id', $user_id, ['forgot_password_status' => '1']);
        if (!empty($user_detail)) {
            $otp = mt_rand(1111, 9999);
            $mail['subject'] = 'Otp For Forgot Password!';
            $mail['message'] = 'Your Otp Is ' . $otp;
            $mail['email'] = $user_detail->email;
            $mail['user_id'] = $user_detail->user_id;
            $mail['name'] = $user_detail->name;
            $mail['otp'] = $otp;
            $user_id = $user_detail->user_id;
            $result = $this->sendPasswordResetMail($mail);
            $result = $this->front_model->sendOtp($otp, $user_id);
            if ($result) {
                session()->put('forgot_email', $email);
                update('users', 'user_id', $user_id, ['forgot_password_status' => '0']);
                return response()->json(['result' => 1, 'msg' => 'OTP resent on your mail', 'url' => route('front/passwordOtp', ['user_id' => encryptionId($user_id)])]);
            } else {
                return response()->json(['result' => -1, 'msg' => 'Something went wrong!!!']);
            }
        } else {
            return response()->json(['result' => -1, 'msg' => 'User does not exist.', 'data' => null]);
        }
    }

    public function sendPasswordResetMail($user_detail)
    {
        $encrypted_id = substr(uniqid(), 0, 10) . $user_detail['user_id'] . substr(uniqid(), 0, 10);
        $htmlContent = "<h3>Dear " . $user_detail['name'] . ",</h3>";
        $htmlContent .= "<div style='padding-top:8px;'>To authenticate, Please use the following One Time Password (OTP), Don't share this OTP with anyone:</div><br>";
        $htmlContent .= "Your Otp is ".$user_detail['otp'];
        // $htmlContent .= "<a href='" . route('front/resetpassword/' . $encrypted_id) . "'> Click Here!!</a>";
        $from = "admin@metaval.com";
        $to = $user_detail['email'];
        $subject = "[Metaval] Forgot Password";
        $headers = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
        $headers .= 'From: ' . $from . "\r\n";
        @mail($to, $subject, $htmlContent, $headers);
        return FALSE;
    }

    public function passwordOtp($user_id)
    {
        $data['title'] = "Password OTP";
        $data['email'] = session()->get('forgot_email');
        if (empty($data['email'])) {
            return redirect(route('front/login'));
        }
        $data['user_id'] = ($user_id);
        return $this->loadview('user/password_otp', $data);
    }

    public function otpVerification(Request $Request)
    {
        $user_id = decryptionID($Request->post('user_id'));
        $otp = $Request->post('otp');
        if (@empty($user_id)) {
            return response()->json(['result' => -1, 'msg' => 'User ID is Required!!.']);
        }
        if (@$otp[0] == null) {
            return response()->json(['result' => -1, 'msg' => 'Otp Required.']);
        }
        if (@($otp[1] == null)) {
            return response()->json(['result' => -1, 'msg' => 'Otp Required.']);
        }
        if (@$otp[2] == null) {
            return response()->json(['result' => -1, 'msg' => 'Otp Required.']);
        }
        if (@($otp[3] == null)) {
            return response()->json(['result' => -1, 'msg' => 'Otp Required.']);
        }
        $mainotp = intval($otp[0] . '' . $otp[1] . '' . $otp[2] . '' . $otp[3]);
        $current_time = date('Y-m-d h:i');
        $result = $this->front_model->verifyOtp($mainotp, $user_id);
        if ($result) {
            if (strtotime($result->otp_expiry) < strtotime($current_time)) {
                return response()->json(['result' => -1, 'msg' => 'Otp Expired. Please Request New Otp']);
            }
            $otpverify = $this->front_model->updateVerifyStatus($user_id);
            if ($otpverify) {
                session()->forget('forgot_email');
            }
            return response()->json(['result' => 1, 'msg' => 'Otp Verified Successfully.', 'url' => route('front/resetpassword', ['user_id' => encryptionID($user_id)])]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'Invaid Otp.']);
        }
    }

    public function ResetPassword($user_id)
    {
        $data['title'] = "Reset Password";
        $data['user_detail'] = $this->front_model->getUserDetail($user_id)->where('region', $this->session_region);
        $data['user_id'] = $user_id;
        $id = substr($user_id, 10);
        $user_id = substr($id, 0, -10);
        $forget_password = $this->front_model->getLinkValidity($user_id);
        if ($forget_password->forgot_password_status == 1) {
            $data['forget_password'] = 'expired';
        } else {
            $data['forget_password'] = 'valid';
        }
        $this->front_model->linkValidity($user_id);
        return $this->loadview('user/reset_password', $data);
    }

    public function doResetPassword(Request $request)
    {
        $encrypted_id = $request->post('user_id');
        $RequestData = $request->all();
        $user_id = decryptionID($encrypted_id);
        $validator = Validator::make($RequestData, $rules = [
            'password'        => 'required|min:6',
            'confirmPassword' => 'required|min:6|same:password',
        ], $messages = [
            'required' => 'The :attribute field is required.',
        ]);
        if ($validator->fails()) {
            return response()->json(['result' => 0, 'errors' => $validator->errors()]);
            return false;
        }
        $newpassword = hash('sha256', $request->post('password'));
        $user_id = decryptionID($encrypted_id);
        $result = $this->front_model->doForgotPassword($user_id, $newpassword);
        if (!empty($result)) {
            return response()->json(['result' => 1, 'url' => route('front/login'), 'msg' => 'Password Reset Successfully']);
        } else {
            return response()->json(['result' => -1, 'msg' => 'New Password Cannot Be Same As Old Password.']);
        }
    }

    public function removeCart(Request $request)
    {
        $cart_id = decryptionID($request->post('cart_id'));
        $product_id = decryptionID($request->post('product_id'));
        $session_index = $request->post('session_index');
        if (!empty($cart_id)) {
            $result = delete('cart', 'cart_id', $cart_id);
            if ($result) {
                return response()->json(['result' => 1, 'msg' => 'Item removed from Cart']);
            } else {
                return response()->json(['result' => -1, 'msg' => 'Item Not Removed from Cart']);
            }
        } else {
            $isNotLogin = @session()->get('isNotLogin');
            unset($isNotLogin[$session_index]);
            session()->put('isNotLogin', $isNotLogin);
            return response()->json(['result' => 1, 'msg' => 'Item removed from Cart']);
        }
    }

    public function webSettings()
    {
        $data['title'] = "Web Settings";
        return $this->loadview('user/web_setting', $data);
    }

    public function privacyPolicies()
    {
        $data['title'] = "Privacy Policy";
        $data['setting'] = $this->front_model->getSettingByType('privacy', $this->session_region);
        return $this->loadview('privacy_policy', $data);
    }

    public function termsAndConditions()
    {
        $data['title'] = "Terms & Condition";
        $data['setting'] = $this->front_model->getSettingByType('Terms', $this->session_region);
        return $this->loadview('terms_condition', $data);
    }

    //========================================================== Cart Managenent===============================================================
    public function isLogin()
    {
        return session()->get('mat_user_id');
    }

    public function getCartItem()
    {
        $user_id = $this->isLogin();
        $sessionCart = session()->get('isNotLogin');
        if ($user_id) {
            $dbdata = $this->front_model->getCartData($user_id);
            if (($dbdata)->isNotEmpty()) {
                $i = 0;
                foreach ($dbdata as $row) {
                    $product = $this->front_model->getProductsByProductId($row->product_id);
                    $i++;
                }
            }
            return $dbdata->groupBy('product_id')->toArray();
        } else {
            if ($sessionCart) {
                foreach ($sessionCart as $key => $row) {

                    if ($key !== "") {
                        $product = $this->front_model->getProductsByProductId($key);
                        $sessionCart[$key][0]->product_name = $product->product_name;
                        $sessionCart[$key][0]->product_image = $product->product_image;
                        $sessionCart[$key][0]->product_price = $product->product_price;
                        $sessionCart[$key][0]->product_quantity = $product->product_quantity;
                        // dd($product);
                    } else {
                        unset($sessionCart[$key]);
                    }
                }
                return $sessionCart;
            };
        }
    }

    public function tempUserCartData()
    {
        $is_login = $this->isLogin();
        $sessionCart = session()->get('isNotLogin');
        $usercartdata = $this->front_model->getCartData($is_login)->groupBy('product_id')->toArray();

        $dbcart = [];
        if (!empty($usercartdata)) {
            foreach ($usercartdata as $row) {
                $dbcart[$row[0]->product_id] = $row;
            }
        }
        if ($sessionCart) {
            foreach ($sessionCart as $key => $row) {
                $sessionCart[$key]['mat_user_id'] = $is_login;
            }
        };
        if ($sessionCart) {
            $sessiondata = $sessionCart;
            $keys = array_keys($dbcart);
            if (!empty($sessiondata)) {
                foreach ($sessiondata as $key => $row) {
                    if (in_array($key, $keys)) {
                        $lastqty = $dbcart[$key][0]->qty;
                        $carbyid = $row[0];
                        $qty = $carbyid->qty + $lastqty;
                        $this->front_model->upadateQty(array('qty' => $qty), $carbyid->product_id);
                    } else {

                        $insertdata = (array)($row[0]);
                        if (!empty($insertdata)) {
                            unset($insertdata['product_name']);
                            unset($insertdata['product_image']);
                            unset($insertdata['product_price']);
                            unset($insertdata['product_quantity']);
                            unset($insertdata['totalreview']);
                            unset($insertdata['rating']);
                            $insertdata['user_id'] = $is_login;
                        }
                        $this->front_model->addCartdb($insertdata);
                    }
                }
            }
        }
        session()->forget('isNotLogin');
    }

    public function getDbcartData($user_id)
    {
        return $this->front_model->getCartData($user_id);
    }

    public function addTooCart(Request $request, $product_id)
    {
        $product_id = decryptionID($product_id);
        $sessioncart = session()->get('isNotLogin');
        $qty = $request->post('qty');
        $is_login = $this->isLogin();
        $product_data = $this->front_model->getProductsByProductId($product_id);
        if ($qty > $product_data->product_quantity) {
            return response()->json(['result' => -1, 'msg' => 'Out Of Stock Available  Qty is -' . $product_data->product_quantity, 'url' => '']);
        }
        if ($is_login) {
            $dbcart = [];
            $usercartdata = $this->front_model->getCartData($is_login);
            if ($usercartdata->isNotEmpty()) {
                foreach ($usercartdata as $row) {
                    $dbcart[$row->product_id] = $row;
                }
            }
            if (!empty($dbcart)) {
                $arraydbkey = array_keys($dbcart);
                if (in_array($product_id, $arraydbkey)) {
                    foreach ($dbcart as $key => $value) {
                        if ($key == $product_id) {
                            if ($value->qty >= $product_data->product_quantity) {
                                return response()->json(['result' => -1, 'msg' => 'Out Of Stock Available  Qty is -' . $product_data->product_quantity, 'url' => '']);
                            } else {
                                $dbcart[$product_id]['qty'] = $value->qty + $qty;
                                $this->front_model->upadateQty(array('qty' => $dbcart[$product_id]->qty), $product_id);
                            }
                        }
                        //update query
                    }
                } else {
                    $dbcart[$product_id] = (object)array(
                        'user_id' => $is_login,
                        'product_id' => $product_id,
                        'qty' => $qty,
                    );
                    $this->front_model->addCartdb((array)$dbcart[$product_id]);
                }
            } else {
                $dbcart = array($product_id => (object)array(
                    'user_id' => $is_login,
                    'product_id' => $product_id,
                    'qty' => $qty,
                ));
                $this->front_model->addCartdb((array)$dbcart[$product_id]);
            }
        } else {
            if (isset($sessioncart)) {
                $arraykey = array_keys($sessioncart);
                if (in_array($product_id, $arraykey)) {
                    foreach ($sessioncart as $key => $row) {
                        if ($key == $product_id) {
                            if ($row[0]->qty > $product_data->product_quantity) {
                                return response()->json(['result' => -1, 'msg' => 'Out Of Stock Available  Qty is -' . $product_data->product_quantity, 'url' => '']);
                            } else {
                                $sessioncart[$product_id][0]->qty = $qty;
                                // dd($sessioncart);
                                session()->save();
                            }
                        }
                    }
                } else {
                    session()->push(
                        'isNotLogin.' . $product_id,
                        (object)array(
                            'user_id' => $is_login,
                            'product_id' => $product_id,
                            'qty' => $qty,
                        )
                    );
                    session()->save();
                }
            } else {
                session()->push(
                    'isNotLogin.' . $product_id,
                    (object)array(
                        'user_id' => $is_login,
                        'product_id' => $product_id,
                        'qty' => $qty,
                    )
                );
                session()->save();
            }
        }
        return response()->json(['result' => 1, 'msg' => 'Item Added', 'url' => '']);
    }

    public function updateCartQuantity(Request $Request)
    {
        $product_id = decryptionId($Request->post('product_id'));
        $user_id = $Request->session()->get('mat_user_id');
        $cart_id = $Request->post('cart_id');
        $qty = $Request->post('qty');
        if (empty($user_id)) {
            $this->updateCart($product_id, $qty, $Request);
            $result = 1;
        } else {
            $product_data = $this->front_model->getProductsByProductId($product_id);
            if ($qty > $product_data->product_quantity) {
                return response()->json(['result' => -1, 'msg' => 'Out Of Stock Available  Qty is -' . $product_data->product_quantity, 'url' => '']);
            }
            if (empty($cart_id)) {
                $updateQuantity = $this->front_model->insertCartQuantity($product_id, $user_id, $qty);
            } else {
                $updateQuantity = $this->front_model->updateCartQuantity($cart_id, $qty);
            }
        }
        return response()->json(['result' => 1, 'msg' => 'Cart updated successfully']);
    }

    public function updateCart($product_id, $qty, Request $request)
    {
        $sessioncart = session()->get('isNotLogin');
        $qty = $request->post('qty');
        $is_login = $this->isLogin();
        $product_data = $this->front_model->getProductsByProductId($product_id);
        // dd($product_data);
        if (!empty($product_data)) {
            if ($qty > $product_data->product_quantity) {
                return response()->json(['result' => -1, 'msg' => 'Out Of Stock Available  Qty is -' . $product_data->product_quantity, 'url' => '']);
            }
        }
        if ($is_login) {
            $dbcart = [];
            $usercartdata = $this->front_model->getCartData($is_login);
            if ($usercartdata->isNotEmpty()) {
                foreach ($usercartdata as $row) {
                    $dbcart[$row->product_id] = $row;
                }
            }
            if (!empty($dbcart)) {
                $arraydbkey = array_keys($dbcart);
                if (in_array($product_id, $arraydbkey)) {
                    foreach ($dbcart as $key => $value) {
                        if ($key == $product_id) {
                            if ($value->qty >= $product_data->product_quantity) {
                                return response()->json(['result' => -1, 'msg' => 'Out Of Stock Available  Qty is -' . $product_data->product_quantity, 'url' => '']);
                            } else {
                                $dbcart[$product_id]['qty'] = $value->qty + $qty;
                                $this->front_model->upadateQty(array('qty' => $dbcart[$product_id]->qty), $product_id);
                            }
                        }
                        //update query
                    }
                } else {
                    $dbcart[$product_id] = (object)array(
                        'user_id' => $is_login,
                        'product_id' => $product_id,
                        'qty' => $qty,
                    );
                    $this->front_model->addCartdb((array)$dbcart[$product_id]);
                }
            } else {
                $dbcart = array($product_id => (object)array(
                    'user_id' => $is_login,
                    'product_id' => $product_id,
                    'qty' => $qty,
                ));
                $this->front_model->addCartdb((array)$dbcart[$product_id]);
            }
        } else {
            if (isset($sessioncart)) {
                $arraykey = array_keys($sessioncart);
                if (in_array($product_id, $arraykey)) {
                    foreach ($sessioncart as $key => $row) {
                        if ($key !== "") {
                            if ($key == $product_id) {
                                if ($row[0]->qty > $product_data->product_quantity) {
                                    return response()->json(['result' => -1, 'msg' => 'Out Of Stock Available  Qty is -' . $product_data->product_quantity, 'url' => '']);
                                } else {
                                    $sessioncart[$product_id][0]->qty = $qty;
                                    // dd($sessioncart);
                                    session()->save();
                                }
                            }
                        }
                    }
                } else {
                    session()->push(
                        'isNotLogin.' . $product_id,
                        (object)array(
                            'user_id' => $is_login,
                            'product_id' => $product_id,
                            'qty' => $qty,
                        )
                    );
                    session()->save();
                }
            } else {
                session()->push(
                    'isNotLogin.' . $product_id,
                    (object)array(
                        'user_id' => $is_login,
                        'product_id' => $product_id,
                        'qty' => $qty,
                    )
                );
                session()->save();
            }
        }
    }

    public function cart(Request $Request)
    {
        $data['title'] = 'Cart';
        //------------------------------------------------
        $product_id = decryptionId($Request->post('product_id'));
        $user_id = $Request->session()->get('mat_user_id');
        $cart_id = $Request->post('cart_id');
        $qty = $Request->post('qty');
        if (empty($user_id)) {
            $this->updateCart($product_id, $qty, $Request);
            $result = 1;
        } else {
            $product_data = $this->front_model->getProductsByProductId($product_id);
            if (!empty($product_data)) {
                if ($qty > $product_data->product_quantity) {
                    return response()->json(['result' => -1, 'msg' => 'Out Of Stock Available  Qty is -' . $product_data->product_quantity, 'url' => '']);
                }
            }
            if (empty($cart_id)) {
                $updateQuantity = $this->front_model->insertCartQuantity($product_id, $user_id, $qty);
            } else {
                $updateQuantity = $this->front_model->updateCartQuantity($cart_id, $qty);
            }
        }
        //------------------------------------------------
        $product_id = decryptionID($Request->post('product_id'));
        $data['product_features'] = $this->product_model->getAllProductFeature($product_id);
       
        $similar_product_id = [];
        $data['cartdata'] = $this->getCartItem();

        $data['productcount'] = !empty($data['cartdata']) ? count($data['cartdata']) : 0;
        $product_price = 0;
        $rating = $this->front_model->getReviewByProductId($product_id)->avg('rating');
        $data['rating'] = 0;
        if (!empty($data['cartdata'])) {
            foreach ($data['cartdata'] as $row) {
                $reviewdata = $this->front_model->getReviewByProductId(@$row[0]->product_id);
                $data['product_data'] = @$this->front_model->getProductsByProductId(@$row[0]->product_id);
                $data['product_features'] = $this->product_model->getAllProductFeature($row[0]->product_id);
                $avgrating = $reviewdata->avg('rating');
                $totalreview = $reviewdata->count('review_id');
                $row[0]->rating = !empty($avgrating) ? $avgrating : 0;
                $row[0]->totalreview = !empty($totalreview) ? $totalreview : 0;
                $price = $row[0]->qty * @$row[0]->product_price;
                $product_price += $price;
            }
        }
        $data['cart_price'] = $product_price;
        $data['shipping_charges'] = 0;
        $data['tax'] = 0;
        $data['total_price'] = $data['cart_price'] + $data['shipping_charges'] + $data['tax'];
        // -------------------- Similar Products ----------------------------
        $data['similar_products'] = $this->front_model->getSimilarProducts($similar_product_id);
        return $this->loadview('shop/cart', $data);
    }
}

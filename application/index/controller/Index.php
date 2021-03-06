<?php
namespace app\index\controller;
use think\Controller;
use think\Request;
use think\Db;
use think\facade\Session;
use think\facade\Config;
use app\index\validate\User;

class Index extends Controller{

	public $request;
	public $template;
    public $map_arr;

	public function __construct(Request $request){
        
        $this->request = $request;
  
        parent::__construct();

        if($this->request->isGet()){

        	$data=$this->request->param();
           
        	$this->assign('data',$data);
        }
        
        $data=$this->request->param();
        
        $site=Config::get('site.site');

        //关闭站点
        if($site['status']=='关闭'){

            exit();
        }


        $this->assign('site',$site);

        $view_path='../application/index/view/'.Config::get('template.user_template').'/';

        if(isset($site['type']['mb']) && $site['type']['mb']=="on" && is_mobile()){
            $view_path.="mobile/";
        }

        if(isset($site['type']['wx']) && $site['type']['wx']=="on" && is_weixin()){
            $view_path.="wx/";
        }


        //动态模板
        $this->view->config('view_path', $view_path);
        
    }
    public function index(){
       
       
    	return $this->fetch();
    }
    public function cate(){

        $cateid=$this->request->param();
   

        $map[]=array(
            ['status','=','1'],
            ['pushtime','<=',date('Y-m-d H:i:s')]
        );

        if(!empty($param['cateid'])){
            $map[]=['cateid','=',$param['cateid'] ];
        }

        if(!empty($param['keyword'])){
            $map[]=['title','like',"%".$param['keyword']."%"];
        }

        if(!empty($param['pid'])){
            $map[]=['pid','=',$param['pid']];
        }

        $list = Db::name('news_list')->where($map)->paginate(10,true,$param);
        $page = $list->render();

        $this->assign('list',$list);
        $this->assign('page',$page);

        return $this->fetch($info['template']);

    }

    #分类#
    public function lists(){

        $param=$this->request->param();

        $map[]=array(
            ['status','=','1'],
            ['pushtime','<=',date('Y-m-d H:i:s')]
        );

        if(!empty($param['cateid'])){
            $map[]=['cateid','=',$param['cateid'] ];

            $map_cate=array(
                'cateid'=>$param['cateid'],
                'status'=>'1'
            );
            $cateinfo=Db::name('news_cate')->where($map_cate)->find();
            $this->assign('cateinfo',$cateinfo);
        
        }

        if(!empty($param['keyword'])){
            $map[]=['title','like',"%".$param['keyword']."%"];
        }

        if(!empty($param['pid'])){
            $map[]=['pid','=',$param['pid']];
        }

        $newslist = Db::name('news_list')->where($map)->order('pushtime desc')->paginate(2,false,['query'=>$param]);

        $this->assign('newslist',$newslist);
        $this->assign('page',$newslist->render());


        

        return $this->fetch($cateinfo['template']);

    }
    #内容页#
    public function content(){
        
        $newsid=$this->request->param('newsid');
        $cateid=$this->request->param('cateid');

        $map[]=array(
            ['status','=','1'],
            ['pushtime','<',date('Y-m-d H:i:s')]
        );

        if(!empty($newsid)){
            $map[]=['newsid','=',$newsid];
        }


        if(!empty($cateid)){
            $map[]=['cateid','=',$cateid];
        }

        $newsinfo=Db::name('news_list')->where($map)->find();

        //判断用户是否点赞
        if(!empty(session('user'))){
            $map_user=array(
                'newsid'=>$newsid,
                'userid'=>session('user.userid')
            );
            $newsinfo['iszan']=Db::name('news_zan')->where($map_user)->count('id');
        }
        
        $map=array(
            'newsid'=>$newsid,
            'status'=>'1'
        );
        $newsinfo['comment_num']=Db::name("comment_list")->where($map)->count('id');

        $map_cate=array(
            'cateid'=>$newsinfo['cateid'],
            'status'=>"1"
        );

        $cateinfo=Db::name('news_cate')->where($map_cate)->find();

        $this->assign('cateinfo',$cateinfo);

        Db::name('news_list')->where($map)->setInc('hits');

        $this->assign('comment',$this->commentlist());

        
        $this->assign('newsinfo',$newsinfo);

        return $this->fetch($newsinfo['template']);
    }
    public function  form(){
        if($this->request->isGet()){
            return $this->fetch();
        }else{

        }
    }

    #评论列表#
    public function commentlist(){
        
        $newsid=$this->request->param('newsid');

        $map=array(
            'newsid'=>$newsid,
            'floor'=>'0',
            'status'=>'1'
        );
        $list=Db::name("comment_list")->where($map)->limit(0,10)->order('id desc')->select(); 

        foreach ($list as $k => $v) {
            $where=array(
                'floor'=>$v['id'],
                'newsid'=>$newsid,
                'status'=>'1'
            );
            $list[$k]['list']=Db::name("comment_list")->where($where)->limit(0,6)->select();
            
        }

       return $list;

    }

    #评论#
    public function comment(){

        if($this->request->ispost()){

            $post=$this->request->post();

            $where=array(
                'newsid'=>$post['newsid'],
                'status'=>'1',
                'iscomment'=>'1'
            );
            $count=Db::name('news_list')->where($where)->count('newsid');
            
            if($count=='0' ) $this->error("评论失败！");
                
            if(session('user')== '' ) $this->error("请先登录才可以评论");

            $validate=User::comment($post);
            
            if(isset($validate)) $this->error($validate);

            if( isset($post['pid']) && $post['pid'] > 0 ){
                
                $username=Db::name("comment_list")->where("id",$post['pid'])->value('username');
                $post['content']="回复给 ".$username."：".$post['content'];
            }

            $user=session('user');

            $post=array(
                'newsid'=>empty($post['newsid'])?"0":$post['newsid'],
                'floor'=>empty($post['floor'])?"0":$post['floor'],
                'pid'=>empty($post['pid'])?"0":$post['pid'],
                'nickname'=>$user['nickname'],
                'userid'=>$user['userid'],
                'avatar'=>$user['avatar'],
                'content'=>$post['content'],
                'status'=>"1",
                'addtime'=>date('Y-m-d H:i:s')
            );

            $res=Db::name("comment_list")->insert($post);

            if($res!==false){
                $this->success("评论成功~");
            }else{
                $this->error("评论失败~");
            }
            
        }else{
            //评论列表

            $newsid=input('newsid');

            $page=input('page','1');
            $num=input('num','10');

            $map=array(
                'newsid'=>$newsid,
                'status'=>'1'
            );

            $list= Db::name("comment_list")->where($map)->page("$page,$num")->order('id desc')->select();
            return json($list);exit;
        }
    }

    #点赞#
    public function newszan(){

        if($this->request->ispost()){

            $post=$this->request->post();
            
    
            $count=Db::name('news_zan')->where('newsid',$post['newsid'])->count('id');
            
            if($count>0){
                $this->error("不可以重复点赞");
            }

            //点赞
            /*if($status==''){

            }else if($status =='1'){

            }else if($status =='0'){

            }*/  


            /*$validate=User::comment($post);
            
            if(isset($validate)) $this->error($validate);*/

            /*if( isset($post['pid']) && $post['pid'] > 0 ){
                
                $username=Db::name("comment_list")->where("id",$post['pid'])->value('username');
                $post['content']="回复给 ".$username."：".$post['content'];
            }*/

            $user=session('user');

            $map=array(
                'status'=>'1',
                'newsid'=>$post['newsid']
            );

            $news=Db::name('news_list')->where($map)->field('newsid,title,url')->find();

            if(empty($news)){
                $this->error("新闻不存在");
            }

            $data=array(
                'newsid'=>$news['newsid'],
                'newstitle'=>$news['title'],
                'newsurl'=>$news['url'],
                'nickname'=>$user['nickname'],
                'userid'=>$user['userid'],
                'avatar'=>$user['avatar'],
                'status'=>"1",
                'addtime'=>date('Y-m-d H:i:s')
            );

            $res=Db::name("news_zan")->insert($data);

            $news_up=Db::name('news_list')->where('newsid',$post['newsid'])->setInc('zan');

            if($res!==false && $news_up !==false){
                $this->success("点赞成功~");
            }else{
                $this->error("点赞失败~");
            }
            

        }
    }


    #站点地图#
    public function sitemap($pid="0"){

        $this->assign('list',self::getcatelist(true));

        return $this->fetch();

    }

    #站点地图 递归计算#
    private function getcatelist($list=""){
        
        if(is_array($list)){

            foreach ($list as $k => $v) {

                $arr=Db::name('news_cate')->where('pid',$v['cateid'])->order('catepx desc')->field('cateid,catename,url')->select();

                $list[$k]["list"]=self::getcatelist($arr);
                
            }

            return $list;

        }
        //初始数据
        if($list==true){

             $map=array(
                'pid'=>"0",
                'status'=>'1'
            );

            $list=Db::name('news_cate')->where($map)->order('cateid desc')->field('cateid,catename,url')->select();    
            return self::getcatelist($list);
        }

        //return $list;
    }


   
}

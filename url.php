
<?php 
	 $page = isset($_GET['go']) ? $_GET['go'] : 'home';

    switch($page)
    {
        case "home":
        include("home.php"); 
		break;
			
		case "MoiNb":
        include("MoiNb.php"); 
		break;
		
	    case "Nam":
        include("Nam.php"); 
		break;

		case "user":
        include("user.php"); 
		break;
			
		case "nu":
        include("nu.php"); 
		break;	
			
		 case "cart":
        include("cart.php"); 
		break;
        
			case "add_to_cart":
        include("add_to_cart.php"); 
		break;
			
				case "add_to_cart_n":
        include("add_to_cart_n.php"); 
		break;
			
				case "add_to_cart_nu":
        include("add_to_cart_nu.php"); 
		break;
			
				case "checkout":
        include("checkout.php"); 
		break;
			
				case "login":
        include("login.php"); 
		break;
			
			case "order_success":
        include("order_success.php"); 
		break;
			
		default:
        include("home.php");
    }
?>
	
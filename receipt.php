<?php
//error_reporting(0);
if (!isset($_SESSION)) {
    session_start();
}
include('header.php');

require_once('inc.config.php');
require_once(_ROOT_PATH_ . '/config/AppManager.php');
require_once(_ROOT_PATH_ . '/payment/class.payment.php');
require_once(_ROOT_PATH_ . '/school/class.school.php');
include_once(_ROOT_PATH_ . '/system_mail/send_receipt.php');

$Payment = new Payment();
$school  = new school();
$OrderNumber = null;
print_r($_POST);
if (!empty($_POST) && !empty($_POST['OrderID'])) {
    $PaymentResponse = $Payment->get_payments_records($_POST['OrderID']);
    $PaymentId = $PaymentResponse[0]['Id'];
    $OrderNumber = $PaymentResponse[0]['OrderNumber'];
    $ReasonCode = $PaymentResponse[0]['ReasonCode'];

    if (!empty($OrderNumber)) {
        $schoolResult = $Payment->getSchoolDetailsByOrderNumber($OrderNumber);
        define('SchoolName', !empty($schoolResult[0]['SchoolName']) ? $schoolResult[0]['SchoolName'] : null);
        $pm = AppManager::getPM();
        $ParentId = $PaymentResponse[0]['Parent'];

        $parent_results = $pm->run("SELECT * FROM parent WHERE id='$ParentId'");

        $payment_results = $pm->run("SELECT * FROM payments WHERE OrderNumber='$OrderNumber' AND Parent='$ParentId'");
        $StudentPayment = number_format($payment_results[0]['TotalAmount'], 2, '.', ',');
        $FeeAmount = number_format($payment_results[0]['FeeAmount'], 2, '.', ',');
        $GCT = number_format($payment_results[0]['GCT'], 2, '.', ',');
        $GrandTotal = number_format(($payment_results[0]['TotalAmount'] + $payment_results[0]['FeeAmount'] + $payment_results[0]['GCT']), 2, '.', ',');
        $PaymentDate = date("Y-m-d H:i:s", strtotime($payment_results[0]['PaymentDate']));
        $CardType = ucfirst($payment_results[0]['CardType']);
        $CardNumber = $payment_results[0]['CardLastDigit'];
        $TransactionId = $payment_results[0]['TransactionId'];
        $ReasonCodeDesc = $payment_results[0]['ReasonCodeDesc'];
        $ResponseCode = $payment_results[0]['ResponseCode'];
        $Currency = $payment_results[0]['Currency'];
        $AuthCode = $payment_results[0]['AuthCode'];
        $ParentFullname = $parent_results[0]['FirstName'] . " " . $parent_results[0]['LastName'];
        $ParentEmail = $parent_results[0]['EmailAddress'];
    }

    if (empty($ResponseCode)) {
        $ResponseCode = checkVal('ResponseCode');
        $ReasonCodeDesc = checkVal('ReasonCodeDesc');
        $ReasonCode = checkVal('ReasonCode');
        $AuthCode = checkVal('AuthCode');

        if (!empty($_POST['ResponseCode']) && !empty($_POST['ReasonCode']) && $_POST['ResponseCode'] == 1 && $_POST['ReasonCode'] == 1) {
            //Normal Approval
            if ($Payment->updatePayment3ds($PaymentId, checkVal('ReferenceNo'), checkVal('TransactionId'), checkVal('AuthCode'), checkVal('ResponseCode'), checkVal('ReasonCode'), checkVal('ReasonCodeDesc'), checkVal('OriginalResponseCode'), checkVal('AuthenticationResult'), checkVal('CAVV'), checkVal('ECIIndicator'), checkVal('TransactionStain'), $PaymentStatus = 'Completed')) {
                $Payment->updatePaymentDetailStatus($PaymentId, 'Completed');
            }
            SendMailTrnxSuccess($PaymentId, $ParentEmail, $ParentFullname, $OrderNumber, checkVal('AuthCode'), $PaymentDate, $CardType, $CardNumber, checkVal('ReasonCodeDesc'), $StudentPayment, $FeeAmount, $GCT, $Currency, $GrandTotal);
        } else if (!empty($_POST['ResponseCode']) && $_POST['ResponseCode'] == 2) {
            if ($Payment->updatePayment3ds($PaymentId, checkVal('ReferenceNo'), checkVal('TransactionId'), checkVal('AuthCode'), checkVal('ResponseCode'), checkVal('ReasonCode'), checkVal('ReasonCodeDesc'), checkVal('OriginalResponseCode'), checkVal('AuthenticationResult'), checkVal('CAVV'), checkVal('ECIIndicator'), checkVal('TransactionStain'), $PaymentStatus = 'Declined')) {
                $Payment->updatePaymentDetailStatus($PaymentId, 'Declined');
            }
            SendMailTrnx($PaymentId, $ParentEmail, $ParentFullname, $OrderNumber, checkVal('AuthCode'), $PaymentDate, $CardType, $CardNumber, checkVal('ReasonCodeDesc'), $StudentPayment, $FeeAmount, $GCT, $Currency, $GrandTotal);
        } else if (!empty($_POST['ResponseCode']) && $_POST['ResponseCode'] == 3) {
            if ($Payment->updatePayment3ds($PaymentId, checkVal('ReferenceNo'), checkVal('TransactionId'), checkVal('AuthCode'), checkVal('ResponseCode'), checkVal('ReasonCode'), checkVal('ReasonCodeDesc'), checkVal('OriginalResponseCode'), checkVal('AuthenticationResult'), checkVal('CAVV'), checkVal('ECIIndicator'), checkVal('TransactionStain'), $PaymentStatus = 'Error')) {
                $Payment->updatePaymentDetailStatus($PaymentId, 'Error');
            }
            SendMailTrnx($PaymentId, $ParentEmail, $ParentFullname, $OrderNumber, checkVal('AuthCode'), $PaymentDate, $CardType, $CardNumber, checkVal('ReasonCodeDesc'), $StudentPayment, $FeeAmount, $GCT, $Currency, $GrandTotal);
        } else {
            if ($Payment->updatePayment3ds($PaymentId, checkVal('ReferenceNo'), checkVal('TransactionId'), checkVal('AuthCode'), checkVal('ResponseCode'), checkVal('ReasonCode'), checkVal('ReasonCodeDesc'), checkVal('OriginalResponseCode'), checkVal('AuthenticationResult'), checkVal('CAVV'), checkVal('ECIIndicator'), checkVal('TransactionStain'), $PaymentStatus = 'Failed')) {
                $Payment->updatePaymentDetailStatus($PaymentId, 'Failed');
            }
        }
    }
}

function checkVal($field)
{
    global $_POST;
    if (!empty($_POST[$field])) {
        return $_POST[$field];
    } else {
        return null;
    }
}

function SendMailTrnx($PaymentId, $ParentEmail, $ParentFullname, $OrderNumber, $AuthCode, $PaymentDate, $CardType, $CardNumber, $ReasonCodeDesc, $StudentPayment, $FeeAmount, $GCT, $Currency, $GrandTotal)
{

    $html = "<p>Dear " . $ParentFullname . ",</p>
    <p>Our attempt to process the following transaction for you has failed :- </p>

    <p><b>School:</b> " . SchoolName . " </p>
    <p><b>Transaction Date:</b> " . $PaymentDate . " EST</p>
    <p><b>Transaction Ref No:</b> " . $OrderNumber . "</p>

    <p><b>Card Type:</b> " . $CardType . "</p>
    <p><b>Card Number:</b> xxxx xxxx xxxx " . $CardNumber . "</p>
    <p><b>Currency:</b> " . $Currency . "</p>
    <p><b>Amount:</b> " . $Currency . ' ' . $GrandTotal. "</p>
    
    <br>
    <p>Please check your card details and try again, or contact your bank/card issuer.</p>
    <p>Please keep a copy of this notification for your records.</p>";
    sendMailReceipt($subject = ' Transaction failed ', $ParentEmail, $html);
}
function SendMailTrnxSuccess($PaymentId, $ParentEmail, $ParentFullname, $OrderNumber, $AuthCode, $PaymentDate, $CardType, $CardNumber, $ReasonCodeDesc, $StudentPayment, $FeeAmount, $GCT, $Currency, $GrandTotal)
{
    global $pm;
    $results = $pm->run("SELECT * FROM payment_request pr,payment_details pd,payment_type pt WHERE pd.PaymentId = '$PaymentId' AND pr.PaymentType = pt.id AND pr.ID = pd.PaymentRequestId AND pd.Status = 'Completed'");
    $summary = "";
    foreach ($results as  $value) {
        if(empty($value['PaymentBatchID'])) continue;
        $StudentName = ucfirst($value['FirstName'])." ".ucfirst($value['Surname'])."   ";
        $StudentTag = !empty($value['DateOfBirth']) ? "<i>DOB  ".date("Y-m-d",strtotime($value['DateOfBirth']))."</i>" : " <i>Student ID - ".$value['StudentID']."</i>";
        $PaymentType = $value['description'];
        $PaidTotal     = number_format($value['PaidTotal'], 2, '.', ',');
        $summary .= "<tr><td>".$PaymentType."</td><td align='right'> ".$Currency." ".$PaidTotal."</td></tr>";
    }
    $html = "<p>Dear " . $ParentFullname . ",</p>
    <p>Thank you for using schoolfeepayments.com. Below is your receipt. Your transaction details are as follow:</p>
    <p><b>School:</b> " . SchoolName . " </p>
    <p><b>Transaction Date:</b> " . $PaymentDate . " EST</p>
    <p><b>Authorization Code:</b> " . $AuthCode . "</p>
    <p><b>Transaction Ref No:</b> " . $OrderNumber . "</p>
    <p><b>Currency:</b>". $Currency."</p>
    <p><b>Card Type:</b> " . $CardType . "</p>
    <p><b>Card Number:</b> xxxx xxxx xxxx " . $CardNumber . "</p>
    <p><b>Transaction Status:</b> Transaction success </p><hr>
    <p>Payment Details</p>
    <p>".$StudentName."  (".$StudentTag.")</p>
 <table class=' recp '>
<tr><th align='left'>Payment Type</th><th align='right'>Paid Total</th></tr>".$summary."
</table><br><br>
 <table class=' recp '>
     <tr><th align='left'>Payment</th><th align='right'>Amount</th></tr>
     <tr><td>Student Payment</td><td align='right'>" . $Currency . ' ' . $StudentPayment . "</td></tr>
     <tr><td>Fee</td><td align='right'>" . $Currency . ' ' . $FeeAmount . "</td></tr>
     <tr><td>GCT on Fee</td><td align='right'>" . $Currency . ' ' . $GCT . "</td></tr>
     <tr><td>Total Paid</td><td align='right'>" . $Currency . ' ' . $GrandTotal . "</td></tr>
 </table>
 <br>
 <p>Note: Please print and keep a copy of this receipt for your records. Should you have any queries, the details contained herein will be needed.</p>";

    sendMailReceipt($subject = ' Transaction success - ' . $OrderNumber, $ParentEmail, $html);
}

?>

<link rel="stylesheet" href="css/form-elements.css">
<link rel="stylesheet" href="css/style.css">

<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <div class="col-md-12">
        </div>
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <!-- left column -->
            <div class="col-md-12">
                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title">Payments</h3>

                        <div class="box-tools pull-right">
                            <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                            </button>
                            <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i></button>
                        </div>
                    </div>
                    <!-- /.box-header -->
                    <div class="box-body">
                        <form role="form" action="" method="post" class="col-md-12">
                            <h3 class="box-title">Make a payment for a student</h3>
                            <fieldset>
                                <?php if (!empty($OrderNumber)) {
                                    if ($ReasonCode == 1 && $ResponseCode == 1) {
                                        global $pm;
                                        $results = $pm->run("SELECT * FROM payment_request pr,payment_details pd,payment_type pt WHERE pd.PaymentId = '$PaymentId' AND pr.PaymentType = pt.id AND pr.ID = pd.PaymentRequestId AND pd.Status = 'Completed'");
                                        $summary = "";
                                        foreach ($results as  $value) {
                                            if (empty($value['PaymentBatchID'])) continue;
                                            $StudentName = ucfirst($value['FirstName']) . " " . ucfirst($value['Surname']) . "   ";
                                            $StudentTag = !empty($value['DateOfBirth']) ? "<i>DOB  " . date("Y-m-d", strtotime($value['DateOfBirth'])) . "</i>" : " <i>Student ID - " . $value['StudentID'] . "</i>";
                                            $PaymentType = $value['description'];
                                            $PaidTotal     = number_format($value['PaidTotal'], 2, '.', ',');
                                            $summary .= "<tr><td>" . $PaymentType . "</td><td align='right'> ".$Currency ." ". $PaidTotal . "</td></tr>";
                                        }
                                ?>
                                        <div class="alert alert-success">
                                            <strong>Success!</strong><br> <?php echo $ReasonCodeDesc; ?>
                                        </div>
                                        <p>Dear <?php echo $parent_results[0]['FirstName'] . " " . $parent_results[0]['LastName']; ?></p>
                                        <p>Thank you for using schoolfeepayments.com. Below is your receipt. Your transaction details are as follow:</p>
                                        <table class="table">
                                           
                                            <tr>
                                                <th>Name</th>
                                                <td><?php echo $parent_results[0]['FirstName'] . " " . $parent_results[0]['LastName']; ?></td>
                                            </tr>
                                            <tr>
                                                <th>School</th>
                                                <td><?php echo SchoolName; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Transaction Date</th>
                                                <td><?php echo $PaymentDate." EST"; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Authorization ID</th>
                                                <td><?php echo $AuthCode; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Transaction Ref No</th>
                                                <td><?php echo $OrderNumber; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Currency</th>
                                                <td><?php echo $Currency; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Transaction Amount</th>
                                                <td><?php echo $Currency . ' ' . $GrandTotal; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Card Type</th>
                                                <td><?php echo $CardType; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Card Number</th>
                                                <td><?php echo 'xxxx xxxx xxxx ' . $CardNumber; ?></td>
                                            </tr>
                                        </table>
                                        <p><b>Payment Details</b></p>
                                        <p><?php echo $StudentName . "(" . $StudentTag . ")"; ?></p>
                                        <table class=' table '>
                                            <tr>
                                                <th>Payment Type</th>
                                                <th class="pull-right">Paid Total</th>
                                            </tr><?php echo $summary; ?>
                                        </table><br><br>
                                        <table class="table">
                                            <tr>
                                                <th>Payment</th>
                                                <th>Currency</th>
                                                <th class="pull-right">Amount</th>
                                            </tr>
                                            <tr>
                                                <td>Student Payment</td>
                                                <td><?= $Currency; ?></td>
                                                <td align="right"> <?php echo $Currency . ' ' . $StudentPayment; ?></td>
                                            </tr>
                                            <tr>
                                                <td>Fee</td>
                                                <td><?= $Currency; ?></td>
                                                <td align="right"> <?php echo $Currency . ' ' . $FeeAmount; ?></td>
                                            </tr>
                                            <tr>
                                                <td>GCT on Fee</td>
                                                <td><?= $Currency; ?></td>
                                                <td align="right"> <?php echo $Currency . ' ' . $GCT; ?></td>
                                            </tr>
                                            <tr>
                                                <td>Total Paid</td>
                                                <td><?= $Currency; ?></td>
                                                <td align="right"><?php echo $Currency . ' ' . $GrandTotal; ?></td>
                                            </tr>
                                        </table>
                                        <br>
                                        <p>Note: Please print and keep a copy of this receipt for your records. Should you have any queries, the details contained herein will be needed.</p>
                                    <?php } ?>
                                    <?php if ($ResponseCode == 2 || $ResponseCode == 3) { ?>
                                        <script>
                                            swal("Error!", 'Transaction failed. Please check your card details and try again, or contact your bank/card issuer', "error");
                                        </script>
                                        <div class="alert alert-danger">
                                            <strong>Transaction failed.</strong><br> Please check your card details and try again, or contact your bank/card issuer.
                                        </div>

                                        <table class="table">
                                            <tr>
                                                <th>Name</th>
                                                <td><?php echo $ParentFullname; ?></td>
                                            </tr>
                                            <tr>
                                                <th>School</th>
                                                <td><?php echo SchoolName; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Payment Date</th>
                                                <td><?php echo $PaymentDate." EST"; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Transaction Ref No</th>
                                                <td><?php echo $OrderNumber; ?></td>
                                            </tr> 
                                            <tr>
                                                <th>Card Type</th>
                                                <td><?php echo $CardType; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Card Number</th>
                                                <td><?php echo 'xxxx xxxx xxxx ' . $CardNumber; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Amount</th>
                                                <td><?php echo $Currency . ' ' . $GrandTotal; ?></td>
                                            </tr>
                                        </table>
                                        <p>Note: Please print and keep a copy of this notification for your records.</p>
                                <?php }
                                } else {
                                    echo '<div>Order number could not be found</div>';
                                } ?>

                            </fieldset>
                        </form>
                    </div>
                    <!-- /.box-body -->
                    <div class="box-footer clearfix">
                        <button type="button" id="print_parent_invoice" class="btn btn-default" style="padding-bottom: 10px;margin-right: 10px;color: #444;"><i class="fa fa-print"></i> Print</button>
                        <button type="button" id="btn_finish" class="btn btn-next btnfinish">Finish</button>
                        <img src="dist/img/credit/visa.png" alt="Visa" width="75px">
                        <img src="dist/img/credit/mastercard.png" alt="Mastercard" width="75px">
                        <img src="dist/img/credit/keycard.png" alt="keycard" width="75px">
                        <img src="dist/img/credit/Powered-by-FAC_web.jpg" alt="American Express" width="75px">
                    </div>
                    <!-- /.box-footer -->
                </div>
            </div>
        </div>
        <!-- /.row -->
    </section>
    <!-- /.content -->
</div>
</section><!-- /.content -->
</div><!-- /.content-wrapper -->

<!-- Javascript -->

<script src="js/scripts.js"></script>
<script src="js/jquery.backstretch.min.js"></script>
<script src="js/retina-1.1.0.min.js"></script>

<?php
$_SESSION['JsonDecoded'] = null;
include "footer.php"; ?>

<script>
    $(document).ready(function() {
        $('.datepicker').datepicker({
            format: 'yyyy/mm/dd',
        });
    });

    $(document).on("click", '#print_parent_invoice', function(e) {
        window.print();
    });
    $(document).on("click", '#btn_finish', function(e) {
        window.location.href = 'https://test.schoolfeepayments.com/app/make_payment.php';
    });
</script>

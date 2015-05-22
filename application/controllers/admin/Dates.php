<?php

namespace admin;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

use general\Tools;

class Dates
{
    public function skipDates(Request $req, Application $app)
    {
        $msg = $req->get('message');
        $dql = "SELECT d FROM models\Dates d 
                WHERE d.view_status = 5 ORDER BY d.skip_date ASC";

        $dql = $app['orm.em']->createQuery($dql);
        $dates = $dql->getResult();

        $view = array(
            'title' => 'Skip Dates',
            'message' => $msg,
            'dates' => $dates,
            'role' => $app['session']->get('is_granted')
        );
        
        return $app['twig']->render('dashboard/dates.twig', $view);
    }

    public function skipDatesAction(Request $req, Application $app)
    {
        $dates = $req->get('date');
        $dateId = (int) $req->get('date_id');
        
        foreach ($dates as $val)
        {
            if ( ! isset($val) || empty($val))
            {
                continue;
            }

            $skip = new \DateTime($val);
            $checkDate = Tools::findBy($app, '\Dates', array('skip_date' => $skip));
            if ( ! empty($checkDate))
            {
                continue;
            }

            if ( ! empty($dateId))
            {
                $date_ = Tools::findOneBy($app, '\Dates', array('id' => $dateId, 'view_status' => 5));

                if (empty($date_))
                {
                    return Tools::redirect($app, 'dates', array('message' => 'invalid_date_id'));
                }
            } else {
                $date_ = new \models\Dates;
                $date_->setCreatedAt('now');
            }

            $date_->setSkipDate($skip);
            $date_->setViewStatus(5);
            $date_->setModifiedAt('now');
        
            $app['orm.em']->persist($date_);
            $app['orm.em']->flush();
        }

        return Tools::redirect($app, 'dates', array('message' => 'dates_added'));
    }
}
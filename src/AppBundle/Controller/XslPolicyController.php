<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use AppBundle\Entity\XslPolicy;
use AppBundle\Entity\XslPolicyFile;
use AppBundle\Entity\XslPolicyRule;
use AppBundle\Lib\XslPolicy\XslPolicyFormFields;
use AppBundle\Lib\XslPolicy\XslPolicyWriter;

/**
 * @Route("/")
 */
class XslPolicyController extends Controller
{
    /**
     * @Route("/xslPolicy/")
     * @Template()
     */
    public function xslPolicyAction(Request $request)
    {
        $policyList = $this->getDoctrine()
            ->getRepository('AppBundle:XslPolicyFile')
            ->findByUser($this->getUser());

        $policySystemList = $this->getDoctrine()
            ->getRepository('AppBundle:XslPolicyFile')
            ->findByUser(null);

        if ($this->get('mediaconch_user.quotas')->hasPolicyCreationRights()) {
            $policy = new XslPolicyFile();
            $importPolicyForm = $this->createForm('xslPolicyImport', $policy);
            $importPolicyForm->handleRequest($request);
            if ($importPolicyForm->isValid()) {
                $em = $this->getDoctrine()->getManager();

                // Set user at the creation of the policy
                if (null === $policy->getUser()) {
                    $policy->setUser($this->getUser());
                }

                $em->persist($policy);
                $em->flush();

                $this->get('session')->getFlashBag()->add(
                    'success',
                    'Policy successfully added'
                    );
                return $this->redirect($this->generateUrl('app_xslpolicy_xslpolicyruleedit', array('id' => $policy->getId())));
            }

            $createPolicyForm = $this->createForm('xslPolicyCreate', $policy);
            $createPolicyForm->handleRequest($request);
            if ($createPolicyForm->isValid()) {
                $em = $this->getDoctrine()->getManager();

                // Set user at the creation of the policy
                if (null === $policy->getUser()) {
                    $policy->setUser($this->getUser());
                }

                $tmpNameFile = tempnam(sys_get_temp_dir(), 'policy' . $policy->getUser()->getId());
                $tmpPolicy = new XslPolicy();
                $tmpPolicy->setTitle($policy->getPolicyName())->setDescription($policy->getPolicyDescription());
                $tmpPolicyWriter = new XslPolicyWriter();
                $tmpPolicyWriter->setPolicy($tmpPolicy)->writeXsl($tmpNameFile);
                $tmpFile = new UploadedFile($tmpNameFile, $policy->getPolicyName() . '.xsl', null, null, null, true);
                $policy->setPolicyFile($tmpFile);

                $em->persist($policy);
                $em->flush();

                $this->get('session')->getFlashBag()->add(
                    'success',
                    'Policy successfully added'
                    );
                return $this->redirect($this->generateUrl('app_xslpolicy_xslpolicyruleedit', array('id' => $policy->getId())));
            }
        }

        return array('importPolicyForm' => isset($importPolicyForm) ? $importPolicyForm->createView() : false,
                     'createPolicyForm' => isset($createPolicyForm) ? $createPolicyForm->createView() : false,
                     'policyList' => $policyList,
                     'policySystemList' => $policySystemList,
                     );
    }

    /**
     * @Route("/xslPolicy/delete/{id}", requirements={"id": "\d+"})
     * @Method("GET")
     */
    public function xslPolicyDeleteAction($id)
    {
        $policy = $this->getDoctrine()
            ->getRepository('AppBundle:XslPolicyFile')
            ->findOneBy(array('id' => $id, 'user' => $this->getUser()));

        if (!$policy) {
             $this->get('session')->getFlashBag()->add(
                'danger',
                'Policy not found'
                );
        }
        else {
            $em = $this->getDoctrine()->getManager();
            $em->remove($policy);
            $em->flush();

            $this->get('session')->getFlashBag()->add(
                'success',
                'Policy successfully removed'
                );
        }

        return $this->redirect($this->generateUrl('app_xslpolicy_xslpolicy'));
    }

    /**
     * @Route("/xslPolicy/export/{id}", requirements={"id": "\d+"})
     * @Method("GET")
     */
    public function xslPolicyExportAction($id)
    {
        $policy = $this->getDoctrine()
            ->getRepository('AppBundle:XslPolicyFile')
            ->findOneBy(array('id' => $id, 'user' => $this->getUser()));

        if (!$policy) {
             $this->get('session')->getFlashBag()->add(
                'danger',
                'Policy not found'
                );

            return $this->redirect($this->generateUrl('app_xslpolicy_xslpolicy'));
        }
        else {
            $handler = $this->container->get('vich_uploader.download_handler');
            return $handler->downloadObject($policy, 'policyFile');
        }
    }

    /**
     * @Route("/xslPolicy/system/export/{id}", requirements={"id": "\d+"})
     * @Method("GET")
     */
    public function xslPolicySystemExportAction($id)
    {
        $policy = $this->getDoctrine()
            ->getRepository('AppBundle:XslPolicyFile')
            ->findOneBy(array('id' => $id, 'user' => null));

        if (!$policy) {
             $this->get('session')->getFlashBag()->add(
                'danger',
                'Policy not found'
                );

            return $this->redirect($this->generateUrl('app_xslpolicy_xslpolicy'));
        }
        else {
            $handler = $this->container->get('vich_uploader.download_handler');
            return $handler->downloadObject($policy, 'policyFile');
        }
    }

    /**
     * @Route("/xslPolicy/duplicate/{id}", requirements={"id": "\d+"})
     * @Method("GET")
     */
    public function xslPolicyDuplicateAction($id)
    {
        $policy = $this->getDoctrine()
            ->getRepository('AppBundle:XslPolicyFile')
            ->findOneBy(array('id' => $id, 'user' => $this->getUser()));

        if (!$policy) {
             $this->get('session')->getFlashBag()->add(
                'danger',
                'Policy not found'
                );
        }
        else {
            $duplicatePolicy = clone $policy;
            $helper = $this->container->get('vich_uploader.storage');
            $policyFile = $helper->resolvePath($policy, 'policyFile');
            $duplicatePolicyFilename = str_replace(pathinfo($policy->getPolicyFilename(), PATHINFO_FILENAME), pathinfo($policy->getPolicyFilename(), PATHINFO_FILENAME) . '_duplicate', $policy->getPolicyFilename());
            $duplicatePolicy->setPolicyFilename($duplicatePolicyFilename);
            $duplicatePolicy->setPolicyName($policy->getPolicyName() . ' - duplicate');
            $duplicatePolicyFile = str_replace($policy->getPolicyFilename(), $duplicatePolicyFilename, $policyFile);
            copy($policyFile, $duplicatePolicyFile);

            $em = $this->getDoctrine()->getManager();
            $em->persist($duplicatePolicy);
            $em->flush();

            $this->get('session')->getFlashBag()->add(
                'success',
                'Policy successfully duplicated'
                );
        }

        return $this->redirect($this->generateUrl('app_xslpolicy_xslpolicy'));
    }

    /**
     * @Route("/xslPolicy/edit/{id}/{ruleId}", defaults={"ruleId" = "new"}, requirements={"id": "\d+", "ruleId": "\d+"})
     * @Template()
     */
    public function xslPolicyRuleEditAction($id, $ruleId, Request $request)
    {
        $policy = $this->getDoctrine()
            ->getRepository('AppBundle:XslPolicyFile')
            ->findOneBy(array('id' => $id, 'user' => $this->getUser()));

        if (!$policy) {
             $this->get('session')->getFlashBag()->add(
                'danger',
                'Policy not found'
                );

            return $this->redirect($this->generateUrl('app_xslpolicy_xslpolicy'));
        }

        $helper = $this->container->get('vich_uploader.storage');
        $policyFile = $helper->resolvePath($policy, 'policyFile');

        $parser = $this->get('mco.xslpolicy.parser');
        $parser->loadXsl($policyFile);

        if ('new' == $ruleId) {
            $rule = new XslPolicyRule();
        }
        else {
            if ($parser->getPolicy()->getRules()->containsKey($ruleId)) {
                $rule = $parser->getPolicy()->getRules()->get($ruleId);
                $originalRule = clone $rule;
            }
            else {
                $this->get('session')->getFlashBag()->add(
                    'danger',
                    'Policy rule not found'
                    );

                return $this->redirect($this->generateUrl('app_xslpolicy_xslpolicyruleedit', array('id' => $id)));
            }
        }

        $policyRuleForm = $this->createForm('xslPolicyRule', $rule);
        $policyRuleForm->handleRequest($request);
        if ($policyRuleForm->isValid()) {
            if ('new' == $ruleId) {
                $parser->getPolicy()->getRules()->add($rule);
            }
            else if ($policyRuleForm->get('DuplicateRule')->isClicked())
            {
                if ($rule->getTitle() == $originalRule->getTitle()) {
                    $rule->setTitle($originalRule->getTitle() . ' - duplicate');
                }
                $parser->getPolicy()->getRules()->add($rule);
                $parser->getPolicy()->getRules()->set($ruleId, $originalRule);
            }

            $writer = $this->get('mco.xslpolicy.writer');
            $writer->setPolicy($parser->getPolicy());
            $writer->writeXsl($policyFile);

            $this->get('session')->getFlashBag()->add(
                'success',
                'Policy rule successfully saved'
                );

            return $this->redirect($this->generateUrl('app_xslpolicy_xslpolicyruleedit', array('id' => $id)));
        }
        return array('policyRuleForm' => isset($policyRuleForm) ? $policyRuleForm->createView() : false,
            'policy' => $policy,
            'xslPolicy' => $parser->getPolicy());
    }

    /**
     * @Route("/xslPolicy/deleteRule/{id}/{ruleId}", requirements={"id": "\d+", "ruleId": "\d+"})
     * @Method("GET")
     */
    public function xslPolicyRuleDeleteAction($id, $ruleId, Request $request)
    {
        $policy = $this->getDoctrine()
            ->getRepository('AppBundle:XslPolicyFile')
            ->findOneBy(array('id' => $id, 'user' => $this->getUser()));

        if (!$policy) {
             $this->get('session')->getFlashBag()->add(
                'danger',
                'Policy not found'
                );

            return $this->redirect($this->generateUrl('app_xslpolicy_xslpolicy'));
        }

        $helper = $this->container->get('vich_uploader.storage');
        $policyFile = $helper->resolvePath($policy, 'policyFile');

        $parser = $this->get('mco.xslpolicy.parser');
        $parser->loadXsl($policyFile);

        if ($parser->getPolicy()->getRules()->containsKey($ruleId)) {
            $parser->getPolicy()->getRules()->remove($ruleId);

            $writer = $this->get('mco.xslpolicy.writer');
            $writer->setPolicy($parser->getPolicy());
            $writer->writeXsl($policyFile);

            $this->get('session')->getFlashBag()->add(
                'success',
                'Policy rule successfully removed'
                );
        }
        else {
            $this->get('session')->getFlashBag()->add(
                'danger',
                'Policy rule not found'
                );
        }

        return $this->redirect($this->generateUrl('app_xslpolicy_xslpolicyruleedit', array('id' => $policy->getId())));
    }

    /**
     * @Route("/xslPolicy/fieldsListRule")
     * @Method({"POST"})
     */
    public function xslPolicyRuleFieldsListAction(Request $request) {
        if (! $request->isXmlHttpRequest()) {
            throw new NotFoundHttpException();
        }

        // Get the type value
        $type = $request->request->get('type');

        return new JsonResponse(XslPolicyFormFields::getFields($type));
    }
}

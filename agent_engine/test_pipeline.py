import asyncio
import json
from agent_engine.graph.workflow import ReviewGraphWorkflow
from agent_engine.sample_prs.fixtures import SAMPLE_PYTHON_PR, SAMPLE_NODE_PR, SAMPLE_PHP_PR


async def run_pipeline_test():
    workflow = ReviewGraphWorkflow()
    print("=== Testing Python PR Review ===")
    res_py = await workflow.run(SAMPLE_PYTHON_PR)
    print("Status:", res_py["status"])
    print("Security Score:", res_py["security_score"], "/ 100")
    print("Total Findings:", res_py["total_findings"])
    print(f"Critical: {res_py['critical_count']} | High: {res_py['high_count']} | Medium: {res_py['medium_count']}")
    print(f"Patches Generated: {len(res_py['patches'])}")
    print(f"Inline Comments: {len(res_py['inline_comments'])}")
    for f in res_py["findings"]:
        print(f"  [{f['severity']}] {f['title']} ({f['cwe_id']}) -> {f['file_path']}:{f['line_start']}")

    print("\n=== Testing Node PR Review ===")
    res_node = await workflow.run(SAMPLE_NODE_PR)
    print("Security Score:", res_node["security_score"], "/ 100")
    print("Total Findings:", res_node["total_findings"])
    for f in res_node["findings"]:
        print(f"  [{f['severity']}] {f['title']} ({f['cwe_id']}) -> {f['file_path']}:{f['line_start']}")

    print("\n=== Testing PHP PR Review ===")
    res_php = await workflow.run(SAMPLE_PHP_PR)
    print("Security Score:", res_php["security_score"], "/ 100")
    print("Total Findings:", res_php["total_findings"])
    for f in res_php["findings"]:
        print(f"  [{f['severity']}] {f['title']} ({f['cwe_id']}) -> {f['file_path']}:{f['line_start']}")

    print("\n=== Pipeline Self-Check Succeeded ===")


if __name__ == "__main__":
    asyncio.run(run_pipeline_test())

#!/usr/bin/env python3
import sys
import json

# Simple test script that echoes input
def main():
    try:
        # Read input
        input_data = sys.stdin.read()
        
        # Try to parse JSON
        try:
            parsed = json.loads(input_data)
            # Add confirmation
            parsed["status"] = "success"
            parsed["message"] = "Test executed successfully"
        except:
            # Not valid JSON
            print(json.dumps({
                "status": "error",
                "message": "Received invalid JSON",
                "received": input_data
            }))
            return
            
        # Return result
        print(json.dumps(parsed))
        
    except Exception as e:
        print(json.dumps({
            "status": "error",
            "message": f"Error: {str(e)}"
        }))

if __name__ == "__main__":
    main()